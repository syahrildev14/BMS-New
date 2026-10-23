<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateDeliveryNoteRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SuratJalan;
use App\Services\DeliveryNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryNoteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ops/delivery-notes/index', [
            // One list for both jobs: an Order still needing a SJ (actionable,
            // Generate button shows) AND an Order that already has one or
            // more SJ (so it stays findable/downloadable here forever, even
            // once fully fulfilled — it doesn't just vanish from the page).
            'orders' => Order::where(function ($query) {
                    $query->where(function ($pending) {
                        $pending->whereIn('status', ['SUBMITTED', 'LOCKED', 'PROCESSING'])
                            ->whereHas('orderItems', fn ($q) => $q->whereNull('surat_jalan_id'));
                    })->orWhereHas('suratJalans');
                })
                ->with(['dapur', 'suratJalans:id,order_id,sj_number,type,pdf_url,tanda_terima_pdf_url'])
                ->withCount(['orderItems as pending_items_count' => fn ($query) => $query->whereNull('surat_jalan_id')])
                ->withCount('suratJalans')
                ->latest('delivery_date')
                ->get(),
            // Items stuck forever (surat_jalan_id stays null) because their DRY_GOODS
            // item has no default_supplier_id to auto-draft a PO against — queried
            // live so it self-resolves once the item master is fixed, no separate
            // "resolved" bookkeeping needed.
            'stuckItems' => OrderItem::whereNull('surat_jalan_id')
                ->whereHas('item', fn ($query) => $query->where('category', 'DRY_GOODS')->whereNull('default_supplier_id'))
                ->with(['item', 'order'])
                ->get()
                ->map(fn (OrderItem $orderItem) => [
                    'id' => $orderItem->id,
                    'item_name' => $orderItem->item->name,
                    'order_number' => $orderItem->order->order_number,
                    'qty_ordered' => (float) $orderItem->qty_ordered,
                ])
                ->values(),
        ]);
    }

    public function store(GenerateDeliveryNoteRequest $request, DeliveryNoteService $service): RedirectResponse
    {
        $result = $service->generateSuratJalan(
            $request->validated()['order_id'],
            $request->user()->id
        );

        $messages = [];

        if ($result['surat_jalan']) {
            $messages[] = "Surat Jalan {$result['surat_jalan']->sj_number} berhasil diterbitkan.";
        }

        if ($result['purchase_orders']->isNotEmpty()) {
            $poNumbers = $result['purchase_orders']->pluck('po_number')->implode(', ');
            $messages[] = "Stok kurang untuk sebagian item — draft PO dibuat: {$poNumbers}.";
        }

        if ($result['unresolved_shortfalls']->isNotEmpty()) {
            $itemNames = $result['unresolved_shortfalls']->pluck('item.name')->implode(', ');
            $messages[] = "PERHATIAN: {$itemNames} kurang stok tapi tidak punya default_supplier_id — isi dulu di master Barang supaya draft PO bisa dibuat otomatis.";
        }

        return back()->with('success', implode(' ', $messages));
    }

    public function download(SuratJalan $suratJalan): StreamedResponse
    {
        abort_unless($suratJalan->pdf_url && Storage::disk('local')->exists($suratJalan->pdf_url), 404);

        return Storage::disk('local')->download(
            $suratJalan->pdf_url,
            str_replace('/', '-', $suratJalan->sj_number) . '.pdf'
        );
    }

    public function downloadTandaTerima(SuratJalan $suratJalan): StreamedResponse
    {
        abort_unless($suratJalan->tanda_terima_pdf_url && Storage::disk('local')->exists($suratJalan->tanda_terima_pdf_url), 404);

        return Storage::disk('local')->download(
            $suratJalan->tanda_terima_pdf_url,
            'Tanda-Terima-' . str_replace('/', '-', $suratJalan->sj_number) . '.pdf'
        );
    }
}
