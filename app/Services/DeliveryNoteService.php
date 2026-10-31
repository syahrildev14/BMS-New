<?php

namespace App\Services;

use App\Jobs\GenerateOrderSuratJalanPdfJob;
use App\Jobs\GenerateSuratJalanPdfJob;
use App\Models\AuditLog;
use App\Models\Gudang;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SuratJalan;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeliveryNoteService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly PurchaseOrderService $purchaseOrderService,
    ) {}

    /**
     * Early stock-shortage detection: called right when an Order's items are
     * confirmed (Tier 1 auto-merge, Tier 3 emergency override, or Tier 2
     * approval — see RevisionGateService), not only when Ops happens to click
     * "Generate Surat Jalan". Raising the draft PO here instead gives
     * Purchasing the maximum possible lead time to restock before the
     * delivery is actually due, instead of discovering the shortfall at the
     * same moment Ops is trying to ship.
     *
     * Idempotent: an order_item already fully covered by the combined
     * qty_ordered of its in-flight (DRAFT/SENT/PARTIALLY_RECEIVED) POs is left
     * alone, so calling this again later (e.g. generateSuratJalan()'s own
     * safety-net call) never double-drafts. If Purchasing has edited a draft
     * PO's qty down below what the order actually needs, only the remaining
     * gap is drafted — not the full qty again.
     *
     * @return array{purchase_orders: Collection<int, PurchaseOrder>, unresolved_shortfalls: Collection<int, OrderItem>}
     */
    public function checkStockShortfalls(Order $order, User $user): array
    {
        return DB::transaction(function () use ($order, $user) {
            $gudang = Gudang::query()->firstOrFail();

            $candidates = OrderItem::where('order_id', $order->id)
                ->whereNull('surat_jalan_id')
                ->whereNotIn('id', AuditLog::pendingOrderItemIds($order->id))
                ->whereHas('item', fn ($query) => $query->where('category', 'DRY_GOODS'))
                ->with('item')
                ->lockForUpdate()
                ->get();

            $insufficientItems = $this->partitionByStock($candidates, $gudang->id)['insufficient'];

            $shortfalls = collect();
            $unresolvedShortfalls = collect();

            foreach ($insufficientItems as $orderItem) {
                $item = $orderItem->item;

                $alreadyInFlight = $this->inFlightShortfallQty($order, $item);
                $remainingNeeded = round((float) $orderItem->qty_ordered - $alreadyInFlight, 2);

                if ($remainingNeeded <= 0.0001) {
                    continue;
                }

                if ($item->default_supplier_id) {
                    $shortfalls->push([
                        'item_id' => $item->id,
                        'supplier_id' => $item->default_supplier_id,
                        'qty' => $remainingNeeded,
                        'unit_cost' => (float) $item->base_cost_price,
                    ]);
                } else {
                    $unresolvedShortfalls->push($orderItem);
                }
            }

            if ($unresolvedShortfalls->isNotEmpty()) {
                $this->logUnresolvedShortfalls($order, $user, $unresolvedShortfalls);
            }

            $purchaseOrders = $shortfalls->isNotEmpty()
                ? $this->purchaseOrderService->generateDraftsForShortfall($order, $gudang, $shortfalls, $user)
                : collect();

            return ['purchase_orders' => $purchaseOrders, 'unresolved_shortfalls' => $unresolvedShortfalls];
        });
    }

    /**
     * Decides which DRY_GOODS OrderItem rows the Gudang's current stock can
     * fully cover, and which it can't — accounting for multiple sibling
     * rows of the *same* item in the same batch (e.g. a base order line plus
     * a same-day add-on for the same item). A naive per-row
     * StockService::availableStock() re-query would let every sibling row
     * believe the same units of stock are theirs, since it never subtracts
     * what an earlier row in the same batch already claimed. Rows are
     * claimed in ascending id (submission) order, so earlier rows get first
     * claim on the stock over later ones.
     *
     * @param  Collection<int, OrderItem>  $orderItems
     * @return array{sufficient: Collection<int, OrderItem>, insufficient: Collection<int, OrderItem>}
     */
    private function partitionByStock(Collection $orderItems, int $gudangId): array
    {
        $remainingByItem = [];
        $sufficient = collect();
        $insufficient = collect();

        foreach ($orderItems->sortBy('id') as $orderItem) {
            $itemId = $orderItem->item_id;

            if (! array_key_exists($itemId, $remainingByItem)) {
                $remainingByItem[$itemId] = $this->stockService->availableStock($gudangId, $itemId);
            }

            $needed = (float) $orderItem->qty_ordered;

            if ($remainingByItem[$itemId] >= $needed) {
                $remainingByItem[$itemId] -= $needed;
                $sufficient->push($orderItem);
            } else {
                $insufficient->push($orderItem);
            }
        }

        return ['sufficient' => $sufficient, 'insufficient' => $insufficient];
    }

    /**
     * Sum of qty_ordered across this order's in-flight (DRAFT/SENT/
     * PARTIALLY_RECEIVED) PO lines for this item — not just "does one exist",
     * since Purchasing can edit a draft PO's qty down below what's needed
     * (see PurchaseOrderController::update) before sending it.
     */
    private function inFlightShortfallQty(Order $order, Item $item): float
    {
        return (float) PurchaseOrderItem::where('item_id', $item->id)
            ->whereHas('purchaseOrder', fn ($query) => $query
                ->where('triggered_by_order_id', $order->id)
                ->whereIn('status', ['DRAFT', 'SENT', 'PARTIALLY_RECEIVED']))
            ->sum('qty_ordered');
    }

    /**
     * @param  Collection<int, OrderItem>  $unresolvedShortfalls
     */
    private function logUnresolvedShortfalls(Order $order, User $user, Collection $unresolvedShortfalls): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'STOCK_SHORTFALL_NO_SUPPLIER',
            'description' => "Order #{$order->id} ({$order->order_number}): item DRY_GOODS kurang stok tapi tidak punya default_supplier_id, draft PO tidak bisa dibuat otomatis.",
            'new_data' => [
                'order_id' => $order->id,
                'items' => $unresolvedShortfalls->map(fn (OrderItem $oi) => [
                    'order_item_id' => $oi->id,
                    'item_id' => $oi->item_id,
                    'item_name' => $oi->item->name,
                    'qty_ordered' => (float) $oi->qty_ordered,
                ])->values()->toArray(),
            ],
        ]);
    }

    /**
     * Generate the next Surat Jalan (PRIMARY for the first call, ADDON afterwards)
     * for every order_item that is not yet attached to a Surat Jalan.
     *
     * WET_GOODS ship straight from the Supplier as before. DRY_GOODS are
     * fulfilled from Gudang stock (FIFO) instead — an item short on stock is
     * held back from this Surat Jalan (to go out later as an ADDON once
     * stock arrives). checkStockShortfalls() is called here too as a safety
     * net (it's idempotent), but the normal path already raised the draft PO
     * earlier, when the Order's items were first confirmed.
     *
     * @return array{surat_jalan: ?SuratJalan, purchase_orders: Collection<int, PurchaseOrder>, unresolved_shortfalls: Collection<int, OrderItem>}
     */
    public function generateSuratJalan(int $orderId, int $userId): array
    {
        $result = DB::transaction(function () use ($orderId, $userId) {
            $user = User::findOrFail($userId);
            $order = Order::where('id', $orderId)->lockForUpdate()->firstOrFail();

            if (in_array($order->status, ['CANCELLED', 'COMPLETED'], true)) {
                throw new \RuntimeException("Tidak bisa menerbitkan Surat Jalan untuk Order berstatus {$order->status}.");
            }

            $unassignedItems = OrderItem::where('order_id', $orderId)
                ->whereNull('surat_jalan_id')
                ->whereNotIn('id', AuditLog::pendingOrderItemIds($orderId))
                ->with('item')
                ->lockForUpdate()
                ->get();

            if ($unassignedItems->isEmpty()) {
                throw new \RuntimeException('Tidak ada item baru yang perlu dibuatkan Surat Jalan (atau seluruhnya masih menunggu persetujuan revisi Tier 2).');
            }

            $gudang = Gudang::query()->firstOrFail();
            $shortfallResult = $this->checkStockShortfalls($order, $user);

            $dryGoodsItems = $unassignedItems->filter(fn (OrderItem $oi) => $oi->item->category === 'DRY_GOODS');
            $otherItems = $unassignedItems->reject(fn (OrderItem $oi) => $oi->item->category === 'DRY_GOODS');

            // DRY_GOODS rows the stock can't fully cover stay held back for a
            // later ADDON SJ — already drafted/logged by
            // checkStockShortfalls() above.
            $toAssign = $otherItems->merge($this->partitionByStock($dryGoodsItems, $gudang->id)['sufficient']);

            $purchaseOrders = $shortfallResult['purchase_orders'];
            $unresolvedShortfalls = $shortfallResult['unresolved_shortfalls'];

            if ($toAssign->isEmpty()) {
                return ['surat_jalan' => null, 'purchase_orders' => $purchaseOrders, 'unresolved_shortfalls' => $unresolvedShortfalls];
            }

            $existingSjCount = SuratJalan::where('order_id', $orderId)->count();
            $sjType = $existingSjCount === 0 ? 'PRIMARY' : 'ADDON';
            $sequence = $existingSjCount + 1; // per-Order sequence, stored in sequence_number

            $suratJalan = $this->createSuratJalanWithUniqueNumber($order, $sjType, $sequence, $userId);

            // Official LOCKED trigger (PRD §Module 3): the first PRIMARY Surat
            // Jalan for an Order locks it, which is what lets PricingEngineService
            // start syncing daily WET_GOODS prices onto its order_items.
            if ($sjType === 'PRIMARY' && $order->status === 'SUBMITTED') {
                $order->update(['status' => 'LOCKED']);
            }

            foreach ($toAssign as $orderItem) {
                $item = $orderItem->item;
                $update = ['surat_jalan_id' => $suratJalan->id];

                if ($item->category === 'DRY_GOODS') {
                    $consumption = $this->stockService->consumeFifo(
                        $gudang,
                        $item,
                        (float) $orderItem->qty_ordered,
                        OrderItem::class,
                        $orderItem->id,
                        $user,
                    );

                    $update['final_cost_price'] = $consumption['weighted_unit_cost'];
                    $update['final_selling_price'] = $item->calculateSellingPrice($consumption['weighted_unit_cost']);
                }

                $orderItem->update($update);
            }

            return ['surat_jalan' => $suratJalan, 'purchase_orders' => $purchaseOrders, 'unresolved_shortfalls' => $unresolvedShortfalls];
        });

        $this->dispatchSuratJalanPdfs($result['surat_jalan']);

        return $result;
    }

    /**
     * sj_number's daily-per-dapur sequence is read-then-write — two Surat
     * Jalan for the same Dapur (different Orders, or the same Order's first
     * PRIMARY SJ racing another Order's) generated in quick succession can
     * both read the same count() and collide on the unique sj_number
     * constraint. lockForUpdate() serializes against any already-committed
     * row for that dapur/day, and the retry-on-duplicate-key loop covers the
     * case where both transactions started with zero existing rows to lock
     * in the first place — same approach as
     * PurchaseOrderService::createPurchaseOrderWithUniqueNumber().
     */
    private function createSuratJalanWithUniqueNumber(Order $order, string $sjType, int $sequence, int $userId): SuratJalan
    {
        $maxAttempts = 5;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $dailySequence = SuratJalan::whereHas('order', fn ($query) => $query->where('dapur_id', $order->dapur_id))
                ->whereDate('created_at', now()->toDateString())
                ->lockForUpdate()
                ->count() + 1;

            $sjCode = $sjType === 'PRIMARY'
                ? sprintf('SJ/%s/%s/%02d', now()->format('Ymd'), $order->dapur->code, $dailySequence)
                : sprintf('SJ-ADD/%s/%s/%02d', now()->format('Ymd'), $order->dapur->code, $dailySequence);

            try {
                return SuratJalan::create([
                    'sj_number' => $sjCode,
                    'order_id' => $order->id,
                    'type' => $sjType,
                    'sequence_number' => $sequence,
                    'printed_by' => $userId,
                ]);
            } catch (QueryException $e) {
                $isDuplicateKey = (int) $e->getCode() === 23000;

                if (! $isDuplicateKey || $attempt === $maxAttempts) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Gagal membuat nomor Surat Jalan unik setelah beberapa percobaan.');
    }

    private function dispatchSuratJalanPdfs(?SuratJalan $suratJalan): void
    {
        if (! $suratJalan) {
            return;
        }

        GenerateSuratJalanPdfJob::dispatchSync($suratJalan->id);

        // Dapur-facing download is one combined PDF per Order (covering
        // every PRIMARY/ADDON shipment so far), regenerated on every new SJ.
        GenerateOrderSuratJalanPdfJob::dispatchSync($suratJalan->order_id);
    }
}
