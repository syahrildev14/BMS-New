<?php

namespace App\Services;

use App\Models\Gudang;
use App\Models\Item;
use App\Models\PurchaseOrderItem;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stock on hand is never stored as a standalone balance — it's always
 * derived from SUM(stock_lots.qty_remaining) for a given Gudang+Item, so
 * there is exactly one source of truth to keep consistent. Consumption
 * follows FIFO: oldest `received_at` lot is drawn down first.
 */
class StockService
{
    public function availableStock(int $gudangId, int $itemId): float
    {
        return (float) StockLot::where('gudang_id', $gudangId)
            ->where('item_id', $itemId)
            ->sum('qty_remaining');
    }

    /**
     * Receive goods against a Purchase Order line into a new stock lot.
     */
    public function receive(PurchaseOrderItem $poItem, float $qtyReceived, float $unitCost, User $user): StockLot
    {
        return DB::transaction(function () use ($poItem, $qtyReceived, $unitCost, $user) {
            $purchaseOrder = $poItem->purchaseOrder()->lockForUpdate()->first();

            $lot = StockLot::create([
                'gudang_id' => $purchaseOrder->gudang_id,
                'item_id' => $poItem->item_id,
                'purchase_order_item_id' => $poItem->id,
                'qty_received' => $qtyReceived,
                'qty_remaining' => $qtyReceived,
                'unit_cost' => $unitCost,
                'received_at' => now(),
            ]);

            StockMovement::create([
                'gudang_id' => $lot->gudang_id,
                'item_id' => $lot->item_id,
                'stock_lot_id' => $lot->id,
                'type' => 'PURCHASE_IN',
                'qty' => $qtyReceived,
                'reference_type' => PurchaseOrderItem::class,
                'reference_id' => $poItem->id,
                'created_by' => $user->id,
            ]);

            $poItem->increment('qty_received', $qtyReceived);

            // $allItems is fetched AFTER the increment above, so qty_received here
            // already reflects this receipt — must not be added a second time.
            $allItems = $purchaseOrder->items()->lockForUpdate()->get();
            $fullyReceived = $allItems->every(
                fn (PurchaseOrderItem $i) => (float) $i->qty_received >= (float) $i->qty_ordered
            );

            $purchaseOrder->update([
                'status' => $fullyReceived ? 'RECEIVED' : 'PARTIALLY_RECEIVED',
            ]);

            return $lot;
        });
    }

    /**
     * Reconcile physical stock count against the system balance
     * (SUM(qty_remaining)). A surplus opens a new adjustment lot (so it's
     * consumable FIFO later, priced at the item's last known unit cost); a
     * shortage deducts FIFO from existing lots via deductFromLots(), same
     * order and shortfall guard as consumeFifo(). Every adjustment is written
     * to stock_movements as OPNAME_ADJUST.
     *
     * The system quantity is computed from the same row-locked snapshot used
     * for the shortage deduction (rather than a separate unlocked SUM), so a
     * concurrent consumeFifo()/receive() on the same item can't sneak in
     * between the read and the write and make the delta stale.
     *
     * @return array{system_qty: float, physical_qty: float, delta: float}
     */
    public function recordOpname(Gudang $gudang, Item $item, float $physicalQty, User $user): array
    {
        return DB::transaction(function () use ($gudang, $item, $physicalQty, $user) {
            $lockedLots = StockLot::where('gudang_id', $gudang->id)
                ->where('item_id', $item->id)
                ->where('qty_remaining', '>', 0)
                ->orderBy('received_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $systemQty = (float) $lockedLots->sum('qty_remaining');
            $delta = round($physicalQty - $systemQty, 2);

            if (abs($delta) < 0.01) {
                return ['system_qty' => $systemQty, 'physical_qty' => $physicalQty, 'delta' => 0.0];
            }

            if ($delta > 0) {
                $lastUnitCost = optional($lockedLots->sortByDesc('received_at')->first())->unit_cost ?? $item->base_cost_price;

                $lot = StockLot::create([
                    'gudang_id' => $gudang->id,
                    'item_id' => $item->id,
                    'purchase_order_item_id' => null,
                    'qty_received' => $delta,
                    'qty_remaining' => $delta,
                    'unit_cost' => $lastUnitCost,
                    'received_at' => now(),
                ]);

                StockMovement::create([
                    'gudang_id' => $gudang->id,
                    'item_id' => $item->id,
                    'stock_lot_id' => $lot->id,
                    'type' => 'OPNAME_ADJUST',
                    'qty' => $delta,
                    'reference_type' => User::class,
                    'reference_id' => $user->id,
                    'created_by' => $user->id,
                ]);
            } else {
                $this->deductFromLots($lockedLots, abs($delta), $gudang, $item, 'OPNAME_ADJUST', -1, User::class, $user->id, $user);
            }

            return ['system_qty' => $systemQty, 'physical_qty' => $physicalQty, 'delta' => $delta];
        });
    }

    /**
     * Consume stock FIFO for an outbound reference (e.g. a SuratJalan being
     * generated). Throws if not enough stock — caller must check
     * availableStock() first to decide whether to short the order instead.
     *
     * @return array{lots: Collection<int, StockLot>, total_qty: float, total_cost: float, weighted_unit_cost: float}
     */
    public function consumeFifo(Gudang $gudang, Item $item, float $qty, string $referenceType, int $referenceId, User $user): array
    {
        return DB::transaction(function () use ($gudang, $item, $qty, $referenceType, $referenceId, $user) {
            $lots = StockLot::where('gudang_id', $gudang->id)
                ->where('item_id', $item->id)
                ->where('qty_remaining', '>', 0)
                ->orderBy('received_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $result = $this->deductFromLots($lots, $qty, $gudang, $item, 'ORDER_OUT', 1, $referenceType, $referenceId, $user);

            return [
                'lots' => $result['lots'],
                'total_qty' => $qty,
                'total_cost' => $result['total_cost'],
                'weighted_unit_cost' => $qty > 0 ? $result['total_cost'] / $qty : 0.0,
            ];
        });
    }

    /**
     * Shared FIFO decrement step for consumeFifo() (order fulfillment) and
     * recordOpname() (physical-count shortage): draws down an already
     * row-locked collection of lots oldest-first, writes one stock_movements
     * row per lot touched, and throws if the collection can't cover $qty —
     * both callers need the same "don't silently under-adjust" guarantee.
     *
     * @param  Collection<int, StockLot>  $lots
     * @return array{lots: Collection<int, StockLot>, total_cost: float}
     */
    private function deductFromLots(
        Collection $lots,
        float $qty,
        Gudang $gudang,
        Item $item,
        string $movementType,
        int $qtySign,
        ?string $referenceType,
        ?int $referenceId,
        User $user,
    ): array {
        $remaining = $qty;
        $totalCost = 0.0;
        $consumedLots = collect();

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $take = min((float) $lot->qty_remaining, $remaining);
            $lot->decrement('qty_remaining', $take);

            StockMovement::create([
                'gudang_id' => $gudang->id,
                'item_id' => $item->id,
                'stock_lot_id' => $lot->id,
                'type' => $movementType,
                'qty' => $qtySign * $take,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $user->id,
            ]);

            $totalCost += $take * (float) $lot->unit_cost;
            $remaining -= $take;
            $consumedLots->push($lot);
        }

        if ($remaining > 0.0001) {
            throw new \RuntimeException("Stok {$item->name} di gudang {$gudang->name} tidak cukup untuk memenuhi {$qty}.");
        }

        return ['lots' => $consumedLots, 'total_cost' => $totalCost];
    }
}
