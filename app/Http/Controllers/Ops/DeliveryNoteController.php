<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateDeliveryNoteRequest;
use App\Models\Armada;
use App\Models\Dapur;
use App\Models\DocumentTemplate;
use App\Models\Driver;
use App\Models\OrderItem;
use App\Models\SuratJalan;
use App\Services\DeliveryNoteService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ops issues one delivery document per Dapur per shipment date, covering
 * every supplier:
 *  - Surat Jalan (dry goods from the koperasi, may span several daily Orders)
 *  - Tanda Terima (wet goods delivered directly by the Suppliers, per day)
 */
class DeliveryNoteController extends Controller
{
    public function index(Request $request, DeliveryNoteService $service): Response
    {
        $filters = $request->validate([
            'dapur_id' => ['nullable', 'integer', 'exists:dapurs,id'],
            'document_type' => ['nullable', Rule::in([SuratJalan::SURAT_JALAN, SuratJalan::TANDA_TERIMA])],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $filters['document_type'] ??= SuratJalan::SURAT_JALAN;
        $filters['date'] ??= now('Asia/Jakarta')->toDateString();

        $pendingGroups = isset($filters['dapur_id'])
            ? $service->pendingGroups((int) $filters['dapur_id'], $filters['document_type'], $filters['date'])
                ->map(fn ($lines) => [
                    'supplier_id' => $lines->first()->supplier_id,
                    'supplier_name' => $lines->first()->supplier->name,
                    'items' => $lines->map(fn (OrderItem $line) => [
                        'id' => $line->id,
                        'item_name' => $line->item->name,
                        'satuan' => $line->item->satuan?->name,
                        'qty_ordered' => (float) $line->qty_ordered,
                        'order_number' => $line->order->order_number,
                        'delivery_date' => $line->order->delivery_date->toDateString(),
                    ])->values(),
                ])
                ->values()
            : collect();

        return Inertia::render('ops/delivery-notes/index', [
            'filters' => $filters,
            'dapurs' => Dapur::orderBy('name')->get(['id', 'code', 'name']),
            'pendingGroups' => $pendingGroups,
            'documentTemplates' => DocumentTemplate::active()
                ->whereIn('document_type', [DocumentTemplate::SURAT_JALAN, DocumentTemplate::TANDA_TERIMA])
                ->orderBy('name')
                ->get(['id', 'name', 'document_type', 'placeholders']),
            'armadas' => Armada::where('is_active', true)->orderBy('name')->get(['id', 'name', 'plate_number']),
            'drivers' => Driver::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'documents' => SuratJalan::with(['dapur:id,code,name', 'supplier:id,code,name'])
                ->withCount('orderItems')
                ->latest()
                ->limit(300)
                ->get(['id', 'sj_number', 'document_type', 'dapur_id', 'supplier_id', 'shipment_date', 'type', 'pdf_url', 'tanda_terima_pdf_url', 'created_at']),
        ]);
    }

    public function store(GenerateDeliveryNoteRequest $request, DeliveryNoteService $service): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $result = $service->generateDocuments(
                (int) $validated['dapur_id'],
                $validated['document_type'],
                $validated['date'],
                $request->user()->id,
                array_map(
                    fn ($id) => $id === null ? null : (int) $id,
                    array_intersect_key($validated, array_flip(['document_template_id', 'armada_id', 'driver_id'])),
                ),
            );
        } catch (QueryException $e) {
            // Also a RuntimeException: must not reach the UI with raw SQL.
            throw $e;
        } catch (\RuntimeException $e) {
            return back()->withErrors(['generate' => $e->getMessage()]);
        }

        $messages = [];

        if ($result['documents']->isNotEmpty()) {
            $messages[] = 'Dokumen terbit: '.$result['documents']->pluck('sj_number')->implode(', ').'.';
        }

        if ($result['held_items']->isNotEmpty()) {
            $itemNames = $result['held_items']->pluck('item.name')->unique()->implode(', ');
            $messages[] = "{$result['held_items']->count()} item kering ditahan karena stok gudang kurang ({$itemNames}) — cek Daftar Belanja Purchasing.";
        }

        return back()->with('success', implode(' ', $messages));
    }

    public function download(SuratJalan $suratJalan, DeliveryNoteService $service): StreamedResponse
    {
        $service->ensurePdf($suratJalan);

        return Storage::disk('local')->download(
            $suratJalan->pdf_url,
            str_replace('/', '-', $suratJalan->sj_number).'.pdf'
        );
    }

    public function downloadTandaTerima(SuratJalan $suratJalan): StreamedResponse
    {
        abort_unless($suratJalan->tanda_terima_pdf_url && Storage::disk('local')->exists($suratJalan->tanda_terima_pdf_url), 404);

        return Storage::disk('local')->download(
            $suratJalan->tanda_terima_pdf_url,
            'Tanda-Terima-'.str_replace('/', '-', $suratJalan->sj_number).'.pdf'
        );
    }
}
