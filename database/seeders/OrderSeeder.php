<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Order;
use App\Models\SuratJalan;
use App\Models\User;
use App\Services\DeliveryNoteService;
use App\Services\DeliveryVerificationService;
use App\Services\PricingEngineService;
use App\Services\RevisionGateService;
use App\Services\SplitInvoiceService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Builds a full demo dataset entirely through the real services
 * (RevisionGateService, DeliveryNoteService, SplitInvoiceService) instead of
 * hand-crafted rows, so every page in the app has realistic, internally
 * consistent data: Tier 1/2/3 revisions, Primary+Addon documents, dry lines held
 * for low stock, and a verified Order with draft invoices.
 */
class OrderSeeder extends Seeder
{
    private Collection $items;

    public function run(): void
    {
        if (Order::query()->exists()) {
            return;
        }

        $this->items = Item::all()->keyBy('sku');

        $dapurUsers = collect(range(1, 5))
            ->mapWithKeys(fn (int $i) => [$i => User::where('email', "dapur{$i}@bms.test")->firstOrFail()]);
        $ops = User::where('email', 'ops@bms.test')->firstOrFail();

        $revisionGate = app(RevisionGateService::class);
        $deliveryNotes = app(DeliveryNoteService::class);
        $splitInvoice = app(SplitInvoiceService::class);
        $verification = app(DeliveryVerificationService::class);

        $today = Carbon::now('Asia/Jakarta')->startOfDay();

        // Order #1 — Tier 1, straightforward, still waiting on Ops to generate SJ.
        $order1 = $this->createOrder($dapurUsers[1], $today->copy()->addDay());
        $revisionGate->submitRevision($order1, $this->payload([
            ['sku' => 'BRG-001', 'qty' => 50],
            ['sku' => 'BRG-009', 'qty' => 20],
            ['sku' => 'BRG-014', 'qty' => 5],
        ]), $dapurUsers[1], $today->copy()->setTime(10, 0));

        // Order #2 — Tier 1, then Ops generates SJ: Tepung Terigu is short on
        // stock (seeded at 5kg, order wants 20kg) so it's held back and shows
        // up on Purchasing's Daftar Belanja.
        $order2 = $this->createOrder($dapurUsers[2], $today->copy()->addDays(2));
        $revisionGate->submitRevision($order2, $this->payload([
            ['sku' => 'BRG-001', 'qty' => 30],
            ['sku' => 'BRG-003', 'qty' => 20],
            ['sku' => 'BRG-010', 'qty' => 15],
        ]), $dapurUsers[2], $today->copy()->setTime(9, 0));
        $this->issueDocuments($deliveryNotes, $order2, $ops);

        // Order #3 — Tier 1, Primary SJ, then a same-day-ish add-on item gets
        // its own Addon SJ, then full STT receipt, then split-billed across
        // all 4 suppliers involved (Beras/Gula/Minyak/Daging each differ).
        $order3 = $this->createOrder($dapurUsers[3], $today->copy()->addDay());
        $revisionGate->submitRevision($order3, $this->payload([
            ['sku' => 'BRG-001', 'qty' => 40],
            ['sku' => 'BRG-004', 'qty' => 10],
            ['sku' => 'BRG-011', 'qty' => 10],
        ]), $dapurUsers[3], $today->copy()->setTime(8, 0));
        $this->issueDocuments($deliveryNotes, $order3, $ops);

        $revisionGate->submitRevision($order3, $this->payload([
            ['sku' => 'BRG-002', 'qty' => 5],
        ]), $dapurUsers[3], $today->copy()->setTime(11, 0));
        $this->issueDocuments($deliveryNotes, $order3, $ops);

        // WET_GOODS lines only get a final_selling_price from a nota or a
        // daily price for that specific delivery_date. DailyPriceSeeder only covered "today", so
        // sync the dates these now-LOCKED demo Orders actually need before
        // billing order #3, or its WET_GOODS lines would invoice as Rp 0.
        $this->syncWetGoodsPricesFor([$order2, $order3]);

        $order3->orderItems()->whereNotNull('surat_jalan_id')->get()->each(
            fn ($orderItem) => $orderItem->update(['qty_received' => $orderItem->qty_ordered, 'qty_rejected' => 0])
        );
        $order3->fresh()->suratJalans->each(fn (SuratJalan $document) => $verification->verify($document, $ops));
        $splitInvoice->generateInvoicesForOrder($order3->fresh());

        // Order #4 — Tier 1 base order, then a same-day emergency add-on
        // (Tier 3 override). Still fully pending for Ops to generate SJ.
        $order4 = $this->createOrder($dapurUsers[4], $today->copy());
        $revisionGate->submitRevision($order4, $this->payload([
            ['sku' => 'BRG-001', 'qty' => 25],
            ['sku' => 'BRG-017', 'qty' => 8],
        ]), $dapurUsers[4], $today->copy()->subDay()->setTime(10, 0));
        $revisionGate->submitRevision($order4, $this->payload([
            ['sku' => 'BRG-006', 'qty' => 3],
        ]), $dapurUsers[4], $today->copy()->setTime(14, 0));

        // Order #5 — Tier 2 (submitted in the 17:01-22:00 WIB window for a
        // future delivery date), sitting in Ops' pending-approval queue.
        $order5 = $this->createOrder($dapurUsers[5], $today->copy()->addDays(3));
        $revisionGate->submitRevision($order5, $this->payload([
            ['sku' => 'BRG-011', 'qty' => 8],
            ['sku' => 'BRG-018', 'qty' => 10],
        ]), $dapurUsers[5], $today->copy()->setTime(19, 0));
    }

    /** Issues whichever documents have pending lines: Surat Jalan (dry) and Tanda Terima (wet). */
    private function issueDocuments(DeliveryNoteService $deliveryNotes, Order $order, User $ops): void
    {
        $date = $order->delivery_date->toDateString();

        foreach ([SuratJalan::SURAT_JALAN, SuratJalan::TANDA_TERIMA] as $documentType) {
            if ($deliveryNotes->pendingGroups($order->dapur_id, $documentType, $date)->isNotEmpty()) {
                $deliveryNotes->generateDocuments($order->dapur_id, $documentType, $date, $ops->id);
            }
        }
    }

    /**
     * @param  array<int, Order>  $orders
     */
    private function syncWetGoodsPricesFor(array $orders): void
    {
        $purchasing = User::where('email', 'purchasing@bms.test')->firstOrFail();
        $service = app(PricingEngineService::class);

        $wetGoodsPrices = $this->items
            ->filter(fn (Item $item) => $item->category === 'WET_GOODS')
            ->map(fn (Item $item) => ['item_id' => $item->id, 'cost_price' => (float) $item->base_cost_price])
            ->values()
            ->all();

        collect($orders)
            ->pluck('delivery_date')
            ->unique()
            ->each(fn ($date) => $service->processDailyPrices($wetGoodsPrices, $date->toDateString(), $purchasing->id));
    }

    private function createOrder(User $dapurUser, Carbon $deliveryDate): Order
    {
        return Order::firstOrCreate(
            ['dapur_id' => $dapurUser->dapur_id, 'delivery_date' => $deliveryDate->toDateString()],
            [
                'order_number' => sprintf('ORD/%s/%s', now()->format('Ymd'), $dapurUser->dapur_id),
                'status' => 'DRAFT',
            ]
        );
    }

    /**
     * @param  array<int, array{sku: string, qty: float}>  $lines
     * @return array<int, array{item_id: int, supplier_id: int, qty_ordered: float, estimated_price: float}>
     */
    private function payload(array $lines): array
    {
        return collect($lines)->map(function (array $line) {
            $item = $this->items[$line['sku']];

            return [
                'item_id' => $item->id,
                'supplier_id' => $item->default_supplier_id,
                'qty_ordered' => $line['qty'],
                'estimated_price' => (float) $item->base_selling_price,
            ];
        })->all();
    }
}
