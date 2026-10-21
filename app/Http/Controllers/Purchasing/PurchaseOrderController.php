<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Purchasing reviews auto-drafted POs (raised by DeliveryNoteService when
 * Gudang stock can't cover a DRY_GOODS order line), can still adjust
 * qty/unit_cost before committing, then sends it to the Supplier.
 */
class PurchaseOrderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('purchasing/purchase-orders/index', [
            'purchaseOrders' => PurchaseOrder::with(['supplier', 'gudang', 'triggeredByOrder.dapur', 'items.item.satuan'])
                ->latest()
                ->get(),
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->status === 'DRAFT', 422, 'Hanya PO berstatus DRAFT yang bisa diedit.');

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.qty_ordered' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        foreach ($validated['items'] as $line) {
            $purchaseOrder->items()->whereKey($line['id'])->update([
                'qty_ordered' => $line['qty_ordered'],
                'unit_cost' => $line['unit_cost'],
            ]);
        }

        return back()->with('success', 'Draft PO berhasil diperbarui.');
    }

    public function send(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $service->send($purchaseOrder, $request->user());

        return back()->with('success', "PO {$purchaseOrder->po_number} berhasil dikirim ke supplier.");
    }
}
