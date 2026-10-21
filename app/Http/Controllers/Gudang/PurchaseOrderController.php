<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('gudang/purchase-orders/index', [
            'purchaseOrders' => PurchaseOrder::whereIn('status', ['SENT', 'PARTIALLY_RECEIVED'])
                ->with(['supplier', 'items.item.satuan'])
                ->latest('sent_at')
                ->get(),
        ]);
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder, StockService $service): RedirectResponse
    {
        abort_unless(
            in_array($purchaseOrder->status, ['SENT', 'PARTIALLY_RECEIVED'], true),
            422,
            'PO ini belum dikirim atau sudah selesai diterima.'
        );

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.qty_received' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        foreach ($validated['items'] as $line) {
            $poItem = $purchaseOrder->items()->whereKey($line['id'])->firstOrFail();
            $service->receive($poItem, (float) $line['qty_received'], (float) $line['unit_cost'], $request->user());
        }

        return back()->with('success', "Penerimaan barang untuk PO {$purchaseOrder->po_number} berhasil disimpan.");
    }
}
