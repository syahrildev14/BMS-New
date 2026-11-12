<?php

use App\Models\DailyPrice;
use App\Models\Invoice;
use App\Models\PurchaseNote;
use App\Models\PurchaseOrder;
use App\Models\StockLot;
use App\Models\SuratJalan;
use App\Models\User;
use App\Services\DeliveryNoteService;
use App\Services\PricingEngineService;
use App\Services\RevisionGateService;
use App\Services\StockService;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RoleAndPermissionSeeder::class);
    $this->gudang = makeGudang();
    $this->purchasing = makeUserWithRole('purchasing');
    $this->opsUser = User::factory()->create();
});

/** Wet order line on a generated Tanda Terima for one Dapur. */
function wetTandaTerima($dapur, $item, $supplier, float $qty = 10, string $date = '2030-01-15'): SuratJalan
{
    $order = makeOrder($dapur, ['delivery_date' => $date]);
    makeOrderItem($order, $item, $supplier, ['qty_ordered' => $qty]);

    return app(DeliveryNoteService::class)
        ->generateDocuments($dapur->id, SuratJalan::TANDA_TERIMA, $date, test()->opsUser->id)['documents']->first();
}

it('builds the dry shopping list as unshipped demand minus gudang stock', function () {
    $supplier = makeSupplier(['name' => 'KOP MERAH PUTIH']);
    $beras = makeItem(['name' => 'Beras', 'category' => 'DRY_GOODS', 'default_supplier_id' => $supplier->id]);
    $gula = makeItem(['name' => 'Gula', 'category' => 'DRY_GOODS', 'default_supplier_id' => $supplier->id]);
    $sayur = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplier->id]);
    makeStockLot($this->gudang, $beras, 4, 10000);
    makeStockLot($this->gudang, $gula, 50, 12000);

    $dapurA = makeDapur();
    $dapurB = makeDapur();
    makeOrderItem(makeOrder($dapurA, ['delivery_date' => '2030-01-14']), $beras, $supplier, ['qty_ordered' => 5]);
    makeOrderItem(makeOrder($dapurB, ['delivery_date' => '2030-01-15']), $beras, $supplier, ['qty_ordered' => 6]);
    makeOrderItem(makeOrder($dapurB, ['delivery_date' => '2030-01-16']), $gula, $supplier, ['qty_ordered' => 3]);
    makeOrderItem(makeOrder($dapurA, ['delivery_date' => '2030-01-15']), $sayur, $supplier, ['qty_ordered' => 9]);
    makeOrderItem(makeOrder($dapurA, ['delivery_date' => '2030-01-25']), $beras, $supplier, ['qty_ordered' => 100]);

    $this->actingAs($this->purchasing)
        ->get(route('purchasing.shopping-list.index', ['until' => '2030-01-16']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('purchasing/shopping-list/index')
            ->has('rows', 2)
            ->where('rows.0.item_name', 'Beras')
            ->where('rows.0.needed', 11)
            ->where('rows.0.stock', 4)
            ->where('rows.0.to_buy', 7)
            ->where('rows.0.supplier_name', 'KOP MERAH PUTIH')
            ->where('rows.1.item_name', 'Gula')
            ->where('rows.1.to_buy', 0));
});

it('records a dry nota as new gudang stock at the nota price', function () {
    $supplier = makeSupplier();
    $beras = makeItem(['category' => 'DRY_GOODS', 'margin_type' => 'FIXED_IDR', 'margin_value' => 2000]);

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store'), [
        'supplier_id' => $supplier->id,
        'note_date' => '2030-01-11',
        'reference_number' => 'TOKO-123',
        'items' => [['item_id' => $beras->id, 'qty' => 25, 'unit_price' => 13000]],
    ])->assertSessionHasNoErrors();

    $note = PurchaseNote::firstOrFail();
    expect($note->category)->toBe('DRY_GOODS');
    expect((float) $note->total_amount)->toBe(325000.0);
    expect(app(StockService::class)->availableStock($this->gudang->id, $beras->id))->toBe(25.0);

    $lot = StockLot::firstOrFail();
    expect((float) $lot->unit_cost)->toBe(13000.0);
    expect($lot->purchase_note_item_id)->toBe($note->items()->first()->id);
    expect((float) $beras->fresh()->base_cost_price)->toBe(13000.0);
    expect((float) $beras->fresh()->base_selling_price)->toBe(15000.0);
});

it('rejects wet items on a dry nota', function () {
    $supplier = makeSupplier();
    $sayur = makeItem(['category' => 'WET_GOODS']);

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store'), [
        'supplier_id' => $supplier->id,
        'note_date' => '2030-01-11',
        'items' => [['item_id' => $sayur->id, 'qty' => 1, 'unit_price' => 1000]],
    ])->assertSessionHasErrors('items.0.item_id');

    expect(PurchaseNote::count())->toBe(0);
});

it('prices each Dapur\'s wet lines from its own nota and flags qty differences', function () {
    $supplier = makeSupplier();
    $cabai = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplier->id, 'margin_type' => 'PERCENTAGE', 'margin_value' => 10]);
    $dapurA = makeDapur();
    $dapurB = makeDapur();
    $ttA = wetTandaTerima($dapurA, $cabai, $supplier, 10);
    $ttB = wetTandaTerima($dapurB, $cabai, $supplier, 5);
    $lineA = $ttA->orderItems()->firstOrFail();
    $lineB = $ttB->orderItems()->firstOrFail();

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$ttA, $ttA->orderItems()->value('supplier_id')]), [
        'items' => [['order_item_id' => $lineA->id, 'qty' => 10, 'unit_price' => 40000]],
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$ttB, $ttB->orderItems()->value('supplier_id')]), [
        'items' => [['order_item_id' => $lineB->id, 'qty' => 4, 'unit_price' => 42000]],
    ])->assertSessionHasNoErrors();

    expect((float) $lineA->fresh()->final_cost_price)->toBe(40000.0);
    expect((float) $lineA->fresh()->final_selling_price)->toBe(44000.0);
    expect((float) $lineB->fresh()->final_cost_price)->toBe(42000.0);

    $noteA = PurchaseNote::where('surat_jalan_id', $ttA->id)->firstOrFail();
    $noteB = PurchaseNote::where('surat_jalan_id', $ttB->id)->firstOrFail();
    expect($noteA->has_discrepancy)->toBeFalse();
    expect($noteB->has_discrepancy)->toBeTrue();
    expect($noteB->dapur_id)->toBe($dapurB->id);

    // Latest nota price becomes the daily price + catalog estimate.
    expect((float) DailyPrice::where('item_id', $cabai->id)->whereDate('date', '2030-01-15')->value('cost_price'))->toBe(42000.0);
    expect((float) $cabai->fresh()->base_cost_price)->toBe(42000.0);
});

it('allows only one nota per Tanda Terima and requires every line of it', function () {
    $supplier = makeSupplier();
    $sayur = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplier->id]);
    $ayam = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplier->id]);
    $dapur = makeDapur();
    $order = makeOrder($dapur, ['delivery_date' => '2030-01-15']);
    $lineSayur = makeOrderItem($order, $sayur, $supplier);
    $lineAyam = makeOrderItem($order, $ayam, $supplier);
    $tt = app(DeliveryNoteService::class)->generateDocuments($dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', $this->opsUser->id)['documents']->first();

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$tt, $tt->orderItems()->value('supplier_id')]), [
        'items' => [['order_item_id' => $lineSayur->id, 'qty' => 10, 'unit_price' => 1000]],
    ])->assertSessionHasErrors('items');
    expect(PurchaseNote::count())->toBe(0);

    $full = ['items' => [
        ['order_item_id' => $lineSayur->id, 'qty' => 10, 'unit_price' => 1000],
        ['order_item_id' => $lineAyam->id, 'qty' => 10, 'unit_price' => 2000],
    ]];
    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$tt, $tt->orderItems()->value('supplier_id')]), $full)->assertSessionHasNoErrors();
    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$tt, $tt->orderItems()->value('supplier_id')]), $full)->assertSessionHasErrors('surat_jalan_id');

    expect(PurchaseNote::count())->toBe(1);
});

it('keeps nota prices when a manual daily price is entered later', function () {
    $supplier = makeSupplier();
    $sayur = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplier->id]);
    $dapurA = makeDapur();
    $dapurB = makeDapur();
    $ttA = wetTandaTerima($dapurA, $sayur, $supplier);
    $ttB = wetTandaTerima($dapurB, $sayur, $supplier);
    $lineA = $ttA->orderItems()->firstOrFail();
    $lineB = $ttB->orderItems()->firstOrFail();

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$ttA, $ttA->orderItems()->value('supplier_id')]), [
        'items' => [['order_item_id' => $lineA->id, 'qty' => 10, 'unit_price' => 7000]],
    ])->assertSessionHasNoErrors();

    app(PricingEngineService::class)->processDailyPrices([['item_id' => $sayur->id, 'cost_price' => 9000]], '2030-01-15', $this->purchasing->id);

    expect((float) $lineA->fresh()->final_cost_price)->toBe(7000.0);
    expect((float) $lineB->fresh()->final_cost_price)->toBe(9000.0);
});

it('lets a wet nota be corrected until the invoice is issued', function () {
    $supplier = makeSupplier();
    $sayur = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplier->id]);
    $dapur = makeDapur();
    $tt = wetTandaTerima($dapur, $sayur, $supplier);
    $line = $tt->orderItems()->firstOrFail();

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$tt, $tt->orderItems()->value('supplier_id')]), [
        'items' => [['order_item_id' => $line->id, 'qty' => 10, 'unit_price' => 7000]],
    ]);
    $note = PurchaseNote::firstOrFail();

    $this->actingAs($this->purchasing)->put(route('purchasing.purchase-notes.update', $note), [
        'items' => [['order_item_id' => $line->id, 'qty' => 10, 'unit_price' => 7500]],
    ])->assertSessionHasNoErrors();
    expect((float) $line->fresh()->final_cost_price)->toBe(7500.0);

    Invoice::create([
        'invoice_number' => 'INV/TEST/1',
        'order_id' => $line->order_id,
        'dapur_id' => $dapur->id,
        'supplier_id' => $supplier->id,
        'total_amount' => 1,
        'status' => 'ISSUED',
    ]);

    $this->actingAs($this->purchasing)->put(route('purchasing.purchase-notes.update', $note), [
        'items' => [['order_item_id' => $line->id, 'qty' => 10, 'unit_price' => 1]],
    ])->assertSessionHasErrors('note');
    expect((float) $line->fresh()->final_cost_price)->toBe(7500.0);
});

it('lets a dry nota be corrected only while its stock is untouched', function () {
    $supplier = makeSupplier();
    $beras = makeItem(['category' => 'DRY_GOODS', 'default_supplier_id' => $supplier->id]);
    $payload = fn (float $qty, float $price) => [
        'supplier_id' => $supplier->id,
        'note_date' => '2030-01-11',
        'items' => [['item_id' => $beras->id, 'qty' => $qty, 'unit_price' => $price]],
    ];

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store'), $payload(20, 10000));
    $note = PurchaseNote::firstOrFail();

    $this->actingAs($this->purchasing)->put(route('purchasing.purchase-notes.update', $note), $payload(25, 11000))->assertSessionHasNoErrors();
    expect(StockLot::count())->toBe(1);
    expect((float) StockLot::first()->qty_remaining)->toBe(25.0);
    expect((float) StockLot::first()->unit_cost)->toBe(11000.0);

    // Ship some of it → the lot is consumed → no more corrections.
    $dapur = makeDapur();
    makeOrderItem(makeOrder($dapur, ['delivery_date' => '2030-01-12']), $beras, $supplier, ['qty_ordered' => 5]);
    app(DeliveryNoteService::class)->generateDocuments($dapur->id, SuratJalan::SURAT_JALAN, '2030-01-12', $this->opsUser->id);

    $this->actingAs($this->purchasing)->put(route('purchasing.purchase-notes.update', $note), $payload(30, 1))->assertSessionHasErrors('note');
    expect((float) StockLot::first()->unit_cost)->toBe(11000.0);
});

it('no longer drafts purchase orders when an order is short on stock', function () {
    $supplier = makeSupplier();
    $beras = makeItem(['category' => 'DRY_GOODS', 'default_supplier_id' => $supplier->id]);
    $order = makeOrder(makeDapur(), ['status' => 'DRAFT', 'delivery_date' => '2030-01-15']);

    app(RevisionGateService::class)->submitRevision(
        $order,
        [['item_id' => $beras->id, 'supplier_id' => $supplier->id, 'qty_ordered' => 50, 'estimated_price' => 1000]],
        $this->opsUser,
        Carbon::parse('2026-11-02 10:00:00', 'Asia/Jakarta'),
    );

    expect(PurchaseOrder::count())->toBe(0);
});

it('requires the purchase-notes permission', function () {
    $dapurUser = makeUserWithRole('dapur', ['dapur_id' => makeDapur()->id]);

    $this->actingAs($dapurUser)->get(route('purchasing.purchase-notes.index'))->assertForbidden();
    $this->actingAs($dapurUser)->get(route('purchasing.shopping-list.index'))->assertForbidden();
    $this->actingAs($this->purchasing)->get(route('purchasing.purchase-notes.index'))->assertOk();
});

it('takes one wet nota per supplier on a combined Tanda Terima', function () {
    $sayurSupplier = makeSupplier(['name' => 'Sayur Jaya']);
    $dagingSupplier = makeSupplier(['name' => 'Daging Segar']);
    $sayur = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $sayurSupplier->id]);
    $ayam = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $dagingSupplier->id]);
    $dapur = makeDapur();
    $order = makeOrder($dapur, ['delivery_date' => '2030-01-15']);
    $lineSayur = makeOrderItem($order, $sayur, $sayurSupplier, ['qty_ordered' => 10]);
    $lineAyam = makeOrderItem($order, $ayam, $dagingSupplier, ['qty_ordered' => 5]);

    $documents = app(DeliveryNoteService::class)
        ->generateDocuments($dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', $this->opsUser->id)['documents'];
    expect($documents)->toHaveCount(1);
    $tt = $documents->first();

    $this->actingAs($this->purchasing)->get(route('purchasing.purchase-notes.index'))
        ->assertInertia(fn ($page) => $page->has('pendingTandaTerima', 2));

    // A nota only covers its own supplier's lines.
    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$tt, $sayurSupplier]), [
        'items' => [
            ['order_item_id' => $lineSayur->id, 'qty' => 10, 'unit_price' => 7000],
            ['order_item_id' => $lineAyam->id, 'qty' => 5, 'unit_price' => 40000],
        ],
    ])->assertSessionHasErrors('items');

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$tt, $sayurSupplier]), [
        'items' => [['order_item_id' => $lineSayur->id, 'qty' => 10, 'unit_price' => 7000]],
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$tt, $dagingSupplier]), [
        'items' => [['order_item_id' => $lineAyam->id, 'qty' => 5, 'unit_price' => 40000]],
    ])->assertSessionHasNoErrors();

    expect(PurchaseNote::where('surat_jalan_id', $tt->id)->pluck('supplier_id')->sort()->values()->all())
        ->toBe(collect([$sayurSupplier->id, $dagingSupplier->id])->sort()->values()->all());
    expect((float) $lineSayur->fresh()->final_cost_price)->toBe(7000.0);
    expect((float) $lineAyam->fresh()->final_cost_price)->toBe(40000.0);

    // A second nota for the same supplier is refused.
    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$tt, $sayurSupplier]), [
        'items' => [['order_item_id' => $lineSayur->id, 'qty' => 10, 'unit_price' => 7000]],
    ])->assertSessionHasErrors('surat_jalan_id');

    // Correcting one supplier's nota leaves the other supplier's prices alone.
    $note = PurchaseNote::where('supplier_id', $sayurSupplier->id)->firstOrFail();
    $this->actingAs($this->purchasing)->put(route('purchasing.purchase-notes.update', $note), [
        'items' => [['order_item_id' => $lineSayur->id, 'qty' => 10, 'unit_price' => 7500]],
    ])->assertSessionHasNoErrors();
    expect((float) $lineSayur->fresh()->final_cost_price)->toBe(7500.0);
    expect((float) $lineAyam->fresh()->final_cost_price)->toBe(40000.0);

    $this->actingAs($this->purchasing)->get(route('purchasing.purchase-notes.index'))
        ->assertInertia(fn ($page) => $page->has('pendingTandaTerima', 0));
});
