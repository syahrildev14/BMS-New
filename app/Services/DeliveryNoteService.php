<?php

namespace App\Services;

use App\Models\Gudang;
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
     * Generate the next Surat Jalan (PRIMARY for the first call, ADDON afterwards)
     * for every order_item that is not yet attached to a Surat Jalan.
     *
     * WET_GOODS ship straight from the Supplier as before. DRY_GOODS are
     * fulfilled from Gudang stock (FIFO) instead — an item short on stock is
     * held back from this Surat Jalan (to go out later as an ADDON once
     * stock arrives) and its full shortfall is raised as a DRAFT Purchase
     * Order for Purchasing to review.
     *
     * @return array{surat_jalan: ?SuratJalan, purchase_orders: Collection<int, PurchaseOrder>}
     */
    public function generateSuratJalan(int $orderId, int $userId): array
    {
        return DB::transaction(function () use ($orderId, $userId) {
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

            $toAssign = collect();
            $shortfalls = collect();

            foreach ($unassignedItems as $orderItem) {
                $item = $orderItem->item;

                if ($item->category !== 'DRY_GOODS') {
                    $toAssign->push($orderItem);
                    continue;
                }

                $available = $this->stockService->availableStock($gudang->id, $item->id);

                if ($available >= (float) $orderItem->qty_ordered) {
                    $toAssign->push($orderItem);
                    continue;
                }

                // Not enough stock: hold the whole line back for a later ADDON SJ.
                if ($item->default_supplier_id) {
                    $shortfalls->push([
                        'item_id' => $item->id,
                        'supplier_id' => $item->default_supplier_id,
                        'qty' => (float) $orderItem->qty_ordered,
                        'unit_cost' => (float) $item->base_cost_price,
                    ]);
                }
            }

            $purchaseOrders = $shortfalls->isNotEmpty()
                ? $this->purchaseOrderService->generateDraftsForShortfall($order, $gudang, $shortfalls, $user)
                : collect();

            if ($toAssign->isEmpty()) {
                return ['surat_jalan' => null, 'purchase_orders' => $purchaseOrders];
            }

            $existingSjCount = SuratJalan::where('order_id', $orderId)->count();
            $sjType = $existingSjCount === 0 ? 'PRIMARY' : 'ADDON';
            $sequence = $existingSjCount + 1;

            $sjCode = $sjType === 'PRIMARY'
                ? sprintf('SJ/%s/%s', now()->format('Ymd'), $order->dapur->code)
                : sprintf('SJ-ADD/%s/%s/%02d', now()->format('Ymd'), $order->dapur->code, $sequence);

            $suratJalan = SuratJalan::create([
                'sj_number' => $sjCode,
                'order_id' => $orderId,
                'type' => $sjType,
                'sequence_number' => $sequence,
                'printed_by' => $userId,
            ]);

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

            return ['surat_jalan' => $suratJalan, 'purchase_orders' => $purchaseOrders];
        });
    }
}
