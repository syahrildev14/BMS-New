<?php

namespace Database\Seeders;

use App\Models\Gudang;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/**
 * Seeds Gudang stock for DRY_GOODS via the real PurchaseOrder -> StockService::receive()
 * path (not hand-crafted stock_lots rows), so stock_movements/PO status stay
 * consistent with what the app itself would have produced.
 *
 * Tepung Terigu (BRG-003) is deliberately under-stocked — OrderSeeder relies
 * on that shortfall to exercise the auto-draft-PO flow in DeliveryNoteService.
 * Susu UHT (BRG-008) is left as a PO still in SENT status, so Gudang's
 * "Terima Barang" queue always has a real pending task to act on.
 */
class StockSeeder extends Seeder
{
    public function run(): void
    {
        if (PurchaseOrder::query()->exists()) {
            return;
        }

        $gudang = Gudang::query()->firstOrFail();
        $purchasing = User::where('email', 'purchasing@bms.test')->firstOrFail();
        $gudangUser = User::where('email', 'gudang@bms.test')->firstOrFail();
        $stockService = app(StockService::class);

        $receivedLines = [
            'SUP-BERAS' => [
                ['sku' => 'BRG-001', 'qty' => 500],
            ],
            'SUP-BUMBU' => [
                ['sku' => 'BRG-002', 'qty' => 200],
                ['sku' => 'BRG-003', 'qty' => 5], // intentional shortfall seed
                ['sku' => 'BRG-005', 'qty' => 100],
                ['sku' => 'BRG-006', 'qty' => 80],
                ['sku' => 'BRG-007', 'qty' => 60],
            ],
            'SUP-MINYAK' => [
                ['sku' => 'BRG-004', 'qty' => 150],
            ],
        ];

        $sequence = 1;

        foreach ($receivedLines as $supplierCode => $lines) {
            $supplier = Supplier::where('code', $supplierCode)->firstOrFail();

            $po = PurchaseOrder::create([
                'po_number' => sprintf('PO/INIT/%s/%02d', $supplierCode, $sequence++),
                'supplier_id' => $supplier->id,
                'gudang_id' => $gudang->id,
                'status' => 'SENT',
                'created_by' => $purchasing->id,
                'sent_by' => $purchasing->id,
                'sent_at' => now()->subDays(7),
            ]);

            foreach ($lines as $line) {
                $item = Item::where('sku', $line['sku'])->firstOrFail();

                $poItem = $po->items()->create([
                    'item_id' => $item->id,
                    'qty_ordered' => $line['qty'],
                    'unit_cost' => $item->base_cost_price,
                ]);

                $stockService->receive($poItem, (float) $line['qty'], (float) $item->base_cost_price, $gudangUser);
            }
        }

        // Still-pending PO: Gudang has a real "Terima Barang" task waiting.
        $minyakSupplier = Supplier::where('code', 'SUP-MINYAK')->firstOrFail();
        $susuUht = Item::where('sku', 'BRG-008')->firstOrFail();

        $pendingPo = PurchaseOrder::create([
            'po_number' => 'PO/INIT/SUP-MINYAK/02',
            'supplier_id' => $minyakSupplier->id,
            'gudang_id' => $gudang->id,
            'status' => 'SENT',
            'created_by' => $purchasing->id,
            'sent_by' => $purchasing->id,
            'sent_at' => now()->subDay(),
        ]);

        $pendingPo->items()->create([
            'item_id' => $susuUht->id,
            'qty_ordered' => 100,
            'unit_cost' => $susuUht->base_cost_price,
        ]);
    }
}
