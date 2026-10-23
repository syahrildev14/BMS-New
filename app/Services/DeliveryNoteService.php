<?php

namespace App\Services;

use App\Jobs\GenerateSuratJalanPdfJob;
use App\Models\AuditLog;
use App\Models\Gudang;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\SuratJalan;
use App\Models\User;
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
     * Idempotent: an order_item already covered by an in-flight (DRAFT/SENT/
     * PARTIALLY_RECEIVED) PO is left alone, so calling this again later (e.g.
     * generateSuratJalan()'s own safety-net call) never double-drafts.
     *
     * @return array{purchase_orders: Collection<int, PurchaseOrder>, unresolved_shortfalls: Collection<int, OrderItem>}
     */
    public function checkStockShortfalls(Order $order, User $user): array
    {
        return DB::transaction(function () use ($order, $user) {
            $gudang = Gudang::query()->firstOrFail();

            $candidates = OrderItem::where('order_id', $order->id)
                ->whereNull('surat_jalan_id')
                ->whereHas('item', fn ($query) => $query->where('category', 'DRY_GOODS'))
                ->with('item')
                ->lockForUpdate()
                ->get();

            $shortfalls = collect();
            $unresolvedShortfalls = collect();

            foreach ($candidates as $orderItem) {
                $item = $orderItem->item;
                $available = $this->stockService->availableStock($gudang->id, $item->id);

                if ($available >= (float) $orderItem->qty_ordered) {
                    continue;
                }

                if ($this->hasInFlightShortfallPo($order, $item)) {
                    continue;
                }

                if ($item->default_supplier_id) {
                    $shortfalls->push([
                        'item_id' => $item->id,
                        'supplier_id' => $item->default_supplier_id,
                        'qty' => (float) $orderItem->qty_ordered,
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

    private function hasInFlightShortfallPo(Order $order, Item $item): bool
    {
        return PurchaseOrder::where('triggered_by_order_id', $order->id)
            ->whereIn('status', ['DRAFT', 'SENT', 'PARTIALLY_RECEIVED'])
            ->whereHas('items', fn ($query) => $query->where('item_id', $item->id))
            ->exists();
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

            $unassignedItems = OrderItem::where('order_id', $orderId)
                ->whereNull('surat_jalan_id')
                ->with('item')
                ->lockForUpdate()
                ->get();

            if ($unassignedItems->isEmpty()) {
                throw new \RuntimeException('Tidak ada item baru yang perlu dibuatkan Surat Jalan.');
            }

            $gudang = Gudang::query()->firstOrFail();
            $shortfallResult = $this->checkStockShortfalls($order, $user);

            $toAssign = collect();

            foreach ($unassignedItems as $orderItem) {
                $item = $orderItem->item;

                if ($item->category !== 'DRY_GOODS') {
                    $toAssign->push($orderItem);
                    continue;
                }

                $available = $this->stockService->availableStock($gudang->id, $item->id);

                if ($available >= (float) $orderItem->qty_ordered) {
                    $toAssign->push($orderItem);
                }

                // else: still short — stays held back for a later ADDON SJ.
                // Already drafted/logged by checkStockShortfalls() above.
            }

            $purchaseOrders = $shortfallResult['purchase_orders'];
            $unresolvedShortfalls = $shortfallResult['unresolved_shortfalls'];

            if ($toAssign->isEmpty()) {
                return ['surat_jalan' => null, 'purchase_orders' => $purchaseOrders, 'unresolved_shortfalls' => $unresolvedShortfalls];
            }

            $existingSjCount = SuratJalan::where('order_id', $orderId)->count();
            $sjType = $existingSjCount === 0 ? 'PRIMARY' : 'ADDON';
            $sequence = $existingSjCount + 1; // per-Order sequence, stored in sequence_number

            // sj_number must stay unique across ALL of this Dapur's Orders for
            // the day, not just within one Order — $sequence above is always 1
            // for every Order's first (PRIMARY) SJ, so it can't disambiguate here.
            $dailySequence = SuratJalan::whereHas('order', fn ($query) => $query->where('dapur_id', $order->dapur_id))
                ->whereDate('created_at', now()->toDateString())
                ->count() + 1;

            $sjCode = $sjType === 'PRIMARY'
                ? sprintf('SJ/%s/%s/%02d', now()->format('Ymd'), $order->dapur->code, $dailySequence)
                : sprintf('SJ-ADD/%s/%s/%02d', now()->format('Ymd'), $order->dapur->code, $dailySequence);

            $suratJalan = SuratJalan::create([
                'sj_number' => $sjCode,
                'order_id' => $orderId,
                'type' => $sjType,
                'sequence_number' => $sequence,
                'printed_by' => $userId,
            ]);

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

        if ($result['surat_jalan']) {
            GenerateSuratJalanPdfJob::dispatchSync($result['surat_jalan']->id);
            $result['surat_jalan']->refresh();
        }

        return $result;
    }
}
