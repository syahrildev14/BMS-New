<?php

namespace App\Services;

use App\Models\Gudang;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    /**
     * Group DRY_GOODS stock shortfalls by supplier and raise one DRAFT
     * Purchase Order per supplier for Purchasing to review before sending.
     *
     * @param  Collection<int, array{item_id: int, supplier_id: int, qty: float, unit_cost: float}>  $shortfalls
     * @return Collection<int, PurchaseOrder>
     */
    public function generateDraftsForShortfall(Order $order, Gudang $gudang, Collection $shortfalls, User $user): Collection
    {
        return DB::transaction(function () use ($order, $gudang, $shortfalls, $user) {
            return $shortfalls
                ->groupBy('supplier_id')
                ->map(function (Collection $items, int $supplierId) use ($order, $gudang, $user) {
                    $supplier = Supplier::findOrFail($supplierId);

                    $sequence = PurchaseOrder::where('supplier_id', $supplierId)
                        ->whereDate('created_at', now()->toDateString())
                        ->count() + 1;

                    $purchaseOrder = PurchaseOrder::create([
                        'po_number' => sprintf('PO/%s/%s/%02d', now()->format('Ymd'), $supplier->code, $sequence),
                        'supplier_id' => $supplierId,
                        'gudang_id' => $gudang->id,
                        'triggered_by_order_id' => $order->id,
                        'status' => 'DRAFT',
                        'created_by' => $user->id,
                    ]);

                    foreach ($items as $shortfall) {
                        $purchaseOrder->items()->create([
                            'item_id' => $shortfall['item_id'],
                            'qty_ordered' => $shortfall['qty'],
                            'unit_cost' => $shortfall['unit_cost'],
                        ]);
                    }

                    return $purchaseOrder;
                })
                ->values();
        });
    }

    public function send(PurchaseOrder $purchaseOrder, User $user): PurchaseOrder
    {
        abort_unless($purchaseOrder->status === 'DRAFT', 422, 'Hanya PO berstatus DRAFT yang bisa dikirim.');

        $purchaseOrder->update([
            'status' => 'SENT',
            'sent_by' => $user->id,
            'sent_at' => now(),
        ]);

        return $purchaseOrder;
    }
}
