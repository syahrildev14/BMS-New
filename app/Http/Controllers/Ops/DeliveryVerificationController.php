<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\DeliveryDocumentPhoto;
use App\Models\OrderItem;
use App\Models\SuratJalan;
use App\Services\DeliveryVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Crosscheck H+1: the koperasi compares ordered qty, Dapur's STT, the nota and
 * the arrival photos for each delivery document. Legacy documents (with an
 * order_id, predating per-supplier documents) are out of scope.
 */
class DeliveryVerificationController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate(['tab' => ['nullable', Rule::in(['open', 'verified'])]]);
        $tab = $validated['tab'] ?? 'open';

        $documents = SuratJalan::crosscheckable()
            ->when(
                $tab === 'open',
                fn ($query) => $query->where('verification_status', '!=', SuratJalan::VERIFICATION_VERIFIED)->orderBy('shipment_date'),
                fn ($query) => $query->where('verification_status', SuratJalan::VERIFICATION_VERIFIED)->latest('verified_at'),
            )
            ->with(['dapur:id,name', 'supplier:id,name'])
            ->withCount(['orderItems', 'photos'])
            ->withCount(['orderItems as stt_filled_count' => fn ($query) => $query->whereNotNull('qty_received')])
            ->limit(300)
            ->get(['id', 'sj_number', 'document_type', 'dapur_id', 'supplier_id', 'shipment_date', 'verification_status']);

        return Inertia::render('ops/verifications/index', [
            'tab' => $tab,
            'today' => now('Asia/Jakarta')->toDateString(),
            'documents' => $documents,
        ]);
    }

    public function show(SuratJalan $suratJalan): Response
    {
        abort_unless($suratJalan->isCrosscheckable(), 404);

        $suratJalan->load(['dapur:id,name', 'supplier:id,name', 'verifiedBy:id,name', 'photos', 'purchaseNotes.items']);
        $notaQtyByLine = $suratJalan->purchaseNotes->flatMap->items->pluck('qty', 'order_item_id');
        $lines = $suratJalan->orderItems()->with(['item.satuan', 'order:id,order_number', 'supplier:id,name'])->get();

        return Inertia::render('ops/verifications/show', [
            'document' => [
                'id' => $suratJalan->id,
                'sj_number' => $suratJalan->sj_number,
                'document_type' => $suratJalan->document_type,
                'shipment_date' => $suratJalan->shipment_date?->toDateString(),
                'dapur_name' => $suratJalan->dapur?->name,
                'supplier_name' => $suratJalan->supplier?->name ?? $lines->pluck('supplier.name')->unique()->sort()->implode(', '),
                'verification_status' => $suratJalan->verification_status,
                'verification_notes' => $suratJalan->verification_notes,
                'verified_by_name' => $suratJalan->verifiedBy?->name,
                'verified_at' => $suratJalan->verified_at?->toDateTimeString(),
                'nota_number' => $suratJalan->purchaseNotes->pluck('note_number')->implode(', ') ?: null,
                'photos' => $suratJalan->photos->map(fn (DeliveryDocumentPhoto $photo) => [
                    'id' => $photo->id,
                    'original_name' => $photo->original_name,
                    'url' => route('ops.verifications.photos.show', [$suratJalan, $photo]),
                ])->values(),
                'lines' => $lines
                    ->map(fn (OrderItem $line) => [
                        'id' => $line->id,
                        'item_name' => $line->item->name,
                        'supplier_name' => $line->supplier?->name,
                        'satuan' => $line->item->satuan?->name,
                        'order_number' => $line->order->order_number,
                        'qty_ordered' => (float) $line->qty_ordered,
                        'qty_received' => $line->qty_received === null ? null : (float) $line->qty_received,
                        'qty_rejected' => $line->qty_rejected === null ? null : (float) $line->qty_rejected,
                        'rejection_reason' => $line->rejection_reason,
                        'qty_nota' => $notaQtyByLine->has($line->id) ? (float) $notaQtyByLine[$line->id] : null,
                    ])->values(),
            ],
        ]);
    }

    public function verify(Request $request, SuratJalan $suratJalan, DeliveryVerificationService $service): RedirectResponse
    {
        abort_unless($suratJalan->isCrosscheckable(), 404);

        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $service->verify($suratJalan, $request->user(), $validated['notes'] ?? null);

        return redirect()->route('ops.verifications.index')
            ->with('success', "Dokumen {$suratJalan->sj_number} terverifikasi.");
    }

    public function discrepancy(Request $request, SuratJalan $suratJalan, DeliveryVerificationService $service): RedirectResponse
    {
        abort_unless($suratJalan->isCrosscheckable(), 404);

        $validated = $request->validate(['notes' => ['required', 'string', 'max:2000']]);
        $service->markDiscrepancy($suratJalan, $request->user(), $validated['notes']);

        return back()->with('success', "Dokumen {$suratJalan->sj_number} ditandai ada selisih.");
    }

    public function photo(SuratJalan $suratJalan, DeliveryDocumentPhoto $photo): StreamedResponse
    {
        abort_unless($photo->surat_jalan_id === $suratJalan->id, 404);
        abort_unless(Storage::disk('local')->exists($photo->path), 404);

        return Storage::disk('local')->response($photo->path);
    }
}
