<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
        $groups = PurchaseOrder::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as po_count')
            ->groupBy('date')
            ->orderByDesc('date')
            ->get();

        return Inertia::render('purchasing/purchase-orders/index', [
            'groups' => $groups,
        ]);
    }

    public function byDate(string $date): Response
    {
        return Inertia::render('purchasing/purchase-orders/show', [
            'date' => $date,
            'purchaseOrders' => PurchaseOrder::with(['supplier', 'gudang', 'triggeredByOrder.dapur', 'items.item.satuan'])
                ->whereDate('created_at', $date)
                ->latest()
                ->get(),
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->status === 'DRAFT', 422, 'Hanya PO berstatus DRAFT yang bisa diedit.');

        $request->merge(['po_number' => trim((string) $request->input('po_number'))]);

        $validated = $request->validate([
            // Nomor otomatis hanya usulan; Purchasing boleh menggantinya selama masih DRAFT.
            'po_number' => ['required', 'string', 'max:50', Rule::unique('purchase_orders', 'po_number')->ignore($purchaseOrder->id)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.qty_ordered' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ], [
            'po_number.required' => 'Nomor PO wajib diisi.',
            'po_number.unique' => 'Nomor PO sudah dipakai PO lain.',
        ]);

        DB::transaction(function () use ($purchaseOrder, $validated) {
            $purchaseOrder->update(['po_number' => $validated['po_number']]);

            foreach ($validated['items'] as $line) {
                $purchaseOrder->items()->whereKey($line['id'])->update([
                    'qty_ordered' => $line['qty_ordered'],
                    'unit_cost' => $line['unit_cost'],
                ]);
            }
        });

        return back()->with('success', 'Draft PO berhasil diperbarui.');
    }

    public function send(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $service->send($purchaseOrder, $request->user());

        return back()->with('success', "PO {$purchaseOrder->po_number} berhasil dikirim ke supplier.");
    }
}
