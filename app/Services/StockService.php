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

            $allItems = $purchaseOrder->items()->get();
            $fullyReceived = $allItems->every(fn (PurchaseOrderItem $i) => $i->id === $poItem->id
                ? ($i->qty_received + $qtyReceived) >= $i->qty_ordered
                : $i->qty_received >= $i->qty_ordered);

            $purchaseOrder->update([
                'status' => $fullyReceived ? 'RECEIVED' : 'PARTIALLY_RECEIVED',
            ]);

            return $lot;
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

            $remainingToConsume = $qty;
            $totalCost = 0.0;
            $consumedLots = collect();

            foreach ($lots as $lot) {
                if ($remainingToConsume <= 0) {
                    break;
                }

                $take = min((float) $lot->qty_remaining, $remainingToConsume);

                $lot->decrement('qty_remaining', $take);

                StockMovement::create([
                    'gudang_id' => $gudang->id,
                    'item_id' => $item->id,
                    'stock_lot_id' => $lot->id,
                    'type' => 'ORDER_OUT',
                    'qty' => $take,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'created_by' => $user->id,
                ]);

                $totalCost += $take * (float) $lot->unit_cost;
                $remainingToConsume -= $take;
                $consumedLots->push($lot);
            }

            if ($remainingToConsume > 0.0001) {
                throw new \RuntimeException("Stok {$item->name} di gudang {$gudang->name} tidak cukup untuk memenuhi {$qty}.");
            }

            return [
                'lots' => $consumedLots,
                'total_qty' => $qty,
                'total_cost' => $totalCost,
                'weighted_unit_cost' => $qty > 0 ? $totalCost / $qty : 0.0,
            ];
        });
    }
}
