<?php

use App\Models\DailyPrice;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PurchaseNote;
use App\Models\SupplierBill;
use App\Models\SuratJalan;
use App\Models\User;
use App\Services\DeliveryNoteService;
use App\Services\DeliveryVerificationService;
use App\Services\PricingEngineService;
use App\Services\SplitInvoiceService;
use App\Services\SupplierBillService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RoleAndPermissionSeeder::class);
    $this->gudang = makeGudang();
    $this->dapur = makeDapur(['code' => 'DPR-A']);
    $this->owner = makeUserWithRole('dapur', ['dapur_id' => $this->dapur->id]);
    $this->ops = makeUserWithRole('ops');
    $this->purchasing = makeUserWithRole('purchasing');
    $this->supplier = makeSupplier(['code' => 'SUP']);
    $this->wet = makeItem(['name' => 'Cabai', 'category' => 'WET_GOODS', 'default_supplier_id' => $this->supplier->id, 'base_cost_price' => 12000, 'base_selling_price' => 13200]);
    $this->dry = makeItem(['name' => 'Beras', 'category' => 'DRY_GOODS', 'default_supplier_id' => $this->supplier->id]);
    $this->documents = app(DeliveryNoteService::class);
});

function issueTt($test, ?Order $order = null, array $lineAttrs = []): array
{
    $order ??= makeOrder($test->dapur, ['delivery_date' => '2030-01-15']);
    $line = makeOrderItem($order, $test->wet, $test->supplier, ['qty_ordered' => 10] + $lineAttrs);
    $tt = $test->documents->generateDocuments($test->dapur->id, SuratJalan::TANDA_TERIMA, $order->delivery_date->toDateString(), $test->ops->id)['documents']->first();

    return [$order, $line, $tt];
}

function makeInvoice(Order $order, $supplier, string $status): Invoice
{
    return Invoice::create([
        'invoice_number' => 'INV/'.uniqid(),
        'order_id' => $order->id,
        'dapur_id' => $order->dapur_id,
        'supplier_id' => $supplier->id,
        'total_amount' => 100,
        'status' => $status,
    ]);
}

function makeBill(Order $order, $supplier, string $status): SupplierBill
{
    return SupplierBill::create([
        'bill_number' => 'BILL/'.uniqid(),
        'order_id' => $order->id,
        'dapur_id' => $order->dapur_id,
        'supplier_id' => $supplier->id,
        'total_amount' => 100,
        'status' => $status,
    ]);
}

it('A1: rejects a wet nota with a blank price when goods were delivered', function () {
    [, $line, $tt] = issueTt($this);

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$tt, $tt->orderItems()->value('supplier_id')]), [
        'items' => [['order_item_id' => $line->id, 'qty' => 10, 'unit_price' => '']],
    ])->assertSessionHasErrors('items.0.unit_price');

    expect(PurchaseNote::count())->toBe(0);
    expect($line->fresh()->final_selling_price)->toBeNull();
    expect((float) $this->wet->fresh()->base_cost_price)->toBe(12000.0);
});

it('A1: rejects a dry nota with a zero or blank price', function () {
    foreach (['', 0] as $price) {
        $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store'), [
            'supplier_id' => $this->supplier->id,
            'note_date' => '2030-01-11',
            'items' => [['item_id' => $this->dry->id, 'qty' => 5, 'unit_price' => $price]],
        ])->assertSessionHasErrors('items.0.unit_price');
    }

    expect(PurchaseNote::count())->toBe(0);
});

it('C5: an undelivered wet line (qty 0) does not touch master or daily prices', function () {
    [, $line, $tt] = issueTt($this);

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$tt, $tt->orderItems()->value('supplier_id')]), [
        'items' => [['order_item_id' => $line->id, 'qty' => 0, 'unit_price' => '']],
    ])->assertSessionHasNoErrors();

    expect((float) $this->wet->fresh()->base_cost_price)->toBe(12000.0);
    expect(DailyPrice::where('item_id', $this->wet->id)->exists())->toBeFalse();
    expect(PurchaseNote::firstOrFail()->has_discrepancy)->toBeTrue();
});

it('C5: an older nota does not move the master price backward', function () {
    [, $lineNew, $ttNew] = issueTt($this, makeOrder($this->dapur, ['delivery_date' => '2030-01-20']));
    [, $lineOld, $ttOld] = issueTt($this, makeOrder($this->dapur, ['delivery_date' => '2030-01-10']));

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$ttNew, $ttNew->orderItems()->value('supplier_id')]), [
        'items' => [['order_item_id' => $lineNew->id, 'qty' => 10, 'unit_price' => 13000]],
    ]);
    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$ttOld, $ttOld->orderItems()->value('supplier_id')]), [
        'items' => [['order_item_id' => $lineOld->id, 'qty' => 10, 'unit_price' => 10000]],
    ]);

    expect((float) $this->wet->fresh()->base_cost_price)->toBe(13000.0);
    expect((float) $lineOld->fresh()->final_cost_price)->toBe(10000.0);
});

it('rejects nota amounts that would overflow their decimal columns', function () {
    [, $line, $tt] = issueTt($this);

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$tt, $tt->orderItems()->value('supplier_id')]), [
        'items' => [['order_item_id' => $line->id, 'qty' => 99999999, 'unit_price' => 99999999]],
    ])->assertSessionHasErrors('items.0.unit_price');

    expect(PurchaseNote::count())->toBe(0);
});

it('A2: refuses a first wet nota once the invoice is final', function () {
    [$order, $line, $tt] = issueTt($this);
    makeInvoice($order, $this->supplier, 'PAID');

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$tt, $tt->orderItems()->value('supplier_id')]), [
        'items' => [['order_item_id' => $line->id, 'qty' => 10, 'unit_price' => 5000]],
    ])->assertSessionHasErrors('note');

    expect($line->fresh()->final_cost_price)->toBeNull();
});

it('A3: a wet nota voids DRAFT invoices and bills so stale amounts cannot be approved', function () {
    [$order, $line, $tt] = issueTt($this);
    $invoice = makeInvoice($order, $this->supplier, 'DRAFT');
    $bill = makeBill($order, $this->supplier, 'DRAFT');

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$tt, $tt->orderItems()->value('supplier_id')]), [
        'items' => [['order_item_id' => $line->id, 'qty' => 10, 'unit_price' => 5000]],
    ])->assertSessionHasNoErrors();

    expect($invoice->fresh()->status)->toBe('VOID');
    expect($bill->fresh()->status)->toBe('VOID');

    $invoice->update(['status' => 'DRAFT']);
    $this->actingAs($this->purchasing)->put(route('purchasing.purchase-notes.update', PurchaseNote::firstOrFail()), [
        'items' => [['order_item_id' => $line->id, 'qty' => 10, 'unit_price' => 6000]],
    ])->assertSessionHasNoErrors();

    expect($invoice->fresh()->status)->toBe('VOID');
});

it('A7: refuses to correct a nota once the supplier bill is final', function () {
    [$order, $line, $tt] = issueTt($this);
    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$tt, $tt->orderItems()->value('supplier_id')]), [
        'items' => [['order_item_id' => $line->id, 'qty' => 10, 'unit_price' => 5000]],
    ]);
    makeBill($order, $this->supplier, 'ISSUED');

    $this->actingAs($this->purchasing)->put(route('purchasing.purchase-notes.update', PurchaseNote::firstOrFail()), [
        'items' => [['order_item_id' => $line->id, 'qty' => 10, 'unit_price' => 1]],
    ])->assertSessionHasErrors('note');

    expect((float) $line->fresh()->final_cost_price)->toBe(5000.0);
});

it('A4: regeneration never resets PAID or OVERDUE invoices and bills', function (string $status) {
    $order = makeOrder($this->dapur, ['status' => 'LOCKED']);
    $sj = SuratJalan::create(['sj_number' => 'SJ/'.uniqid(), 'order_id' => $order->id, 'type' => 'PRIMARY', 'printed_by' => $this->ops->id]);
    makeOrderItem($order, $this->wet, $this->supplier, [
        'surat_jalan_id' => $sj->id, 'qty_received' => 1, 'qty_rejected' => 0, 'final_selling_price' => 100, 'final_cost_price' => 100,
    ]);
    $invoice = makeInvoice($order, $this->supplier, $status);
    $bill = makeBill($order, $this->supplier, $status === 'OVERDUE' ? 'ISSUED' : $status);

    app(SplitInvoiceService::class)->generateInvoicesForOrder($order);
    app(SupplierBillService::class)->generateBillsForOrder($order);

    expect($invoice->fresh()->status)->toBe($status);
    expect($bill->fresh()->status)->not->toBe('DRAFT');
})->with(['PAID', 'OVERDUE']);

it('A5: does not complete an Order that still has unshipped lines', function () {
    $order = makeOrder($this->dapur, ['status' => 'LOCKED']);
    $sj = SuratJalan::create(['sj_number' => 'SJ/'.uniqid(), 'order_id' => $order->id, 'type' => 'PRIMARY', 'printed_by' => $this->ops->id]);
    makeOrderItem($order, $this->wet, $this->supplier, [
        'surat_jalan_id' => $sj->id, 'qty_received' => 1, 'qty_rejected' => 0, 'final_selling_price' => 100,
    ]);
    $held = makeOrderItem($order, $this->dry, $this->supplier);

    $service = app(SplitInvoiceService::class);
    $service->approveInvoice($service->generateInvoicesForOrder($order)->first());

    expect($order->fresh()->status)->toBe('LOCKED');

    makeStockLot($this->gudang, $this->dry, 100, 1000);
    $doc = $this->documents->generateDocuments($this->dapur->id, SuratJalan::SURAT_JALAN, $order->delivery_date->toDateString(), $this->ops->id)['documents']->first();
    expect($held->fresh()->surat_jalan_id)->toBe($doc->id);
});

it('re-checks the crosscheck when a DRAFT invoice is approved', function () {
    [$order, $line, $tt] = issueTt($this, null, ['final_selling_price' => 100, 'final_cost_price' => 90]);
    $line->update(['qty_received' => 10, 'qty_rejected' => 0]);
    $invoice = makeInvoice($order, $this->supplier, 'DRAFT');

    expect(fn () => app(SplitInvoiceService::class)->approveInvoice($invoice))->toThrow(ValidationException::class);
    expect($invoice->fresh()->status)->toBe('DRAFT');
});

it('A7: reopening a verified document is refused when the supplier bill is final, and voids draft bills otherwise', function () {
    [$order, $line, $tt] = issueTt($this);
    $line->update(['qty_received' => 10, 'qty_rejected' => 0]);
    $verification = app(DeliveryVerificationService::class);
    $verification->verify($tt, $this->ops);

    $draft = makeBill($order, $this->supplier, 'DRAFT');
    $verification->markDiscrepancy($tt, $this->ops, 'cek');
    expect($draft->fresh()->status)->toBe('VOID');

    $verification->verify($tt, $this->ops);
    $draft->update(['status' => 'ISSUED']);
    expect(fn () => $verification->markDiscrepancy($tt, $this->ops, 'lagi'))->toThrow(ValidationException::class);
});

it('B7: a pending document can record a discrepancy even when its invoice pair is issued', function () {
    [$order, , $tt] = issueTt($this);
    makeInvoice($order, $this->supplier, 'ISSUED');

    app(DeliveryVerificationService::class)->markDiscrepancy($tt, $this->ops, 'Kurang 2 kg');

    expect($tt->fresh()->verification_status)->toBe('DISCREPANCY');
});

it('does not overwrite who verified a document on a repeated verify', function () {
    [, $line, $tt] = issueTt($this);
    $line->update(['qty_received' => 10, 'qty_rejected' => 0]);
    $verification = app(DeliveryVerificationService::class);
    $verification->verify($tt, $this->ops, 'pertama');

    expect(fn () => $verification->verify($tt, User::factory()->create(), 'kedua'))->toThrow(ValidationException::class);
    expect($tt->fresh()->verified_by)->toBe($this->ops->id);
    expect($tt->fresh()->verification_notes)->toBe('pertama');
});

it('B1: lets Dapur record STT for one arrived document while another has not arrived', function () {
    makeStockLot($this->gudang, $this->dry, 100, 1000);
    $order = makeOrder($this->dapur, ['delivery_date' => '2030-01-15']);
    $wetLine = makeOrderItem($order, $this->wet, $this->supplier, ['qty_ordered' => 10]);
    $dryLine = makeOrderItem($order, $this->dry, $this->supplier, ['qty_ordered' => 10]);
    $this->documents->generateDocuments($this->dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', $this->ops->id);
    $this->documents->generateDocuments($this->dapur->id, SuratJalan::SURAT_JALAN, '2030-01-15', $this->ops->id);

    $this->actingAs($this->owner)->post(route('dapur.orders.stt', $order), [
        'items' => [['id' => $wetLine->id, 'qty_received' => 10, 'qty_rejected' => 0]],
    ])->assertSessionHasNoErrors();

    expect($dryLine->fresh()->qty_received)->toBeNull();
});

it('B3: refuses a new order for a date whose Order is closed', function (string $status) {
    makeOrder($this->dapur, ['delivery_date' => '2030-01-15', 'status' => $status]);

    $this->actingAs($this->owner)->post(route('dapur.orders.store'), [
        'delivery_date' => '2030-01-15',
        'items' => [['item_id' => $this->wet->id, 'qty_ordered' => 1]],
    ])->assertSessionHasErrors('delivery_date');

    expect(Order::firstOrFail()->orderItems()->count())->toBe(0);
})->with(['CANCELLED', 'COMPLETED']);

it('B4: limits one photo upload to what the server accepts', function () {
    [, , $tt] = issueTt($this);
    $files = array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.jpg")->size(5000), range(1, 5));

    $this->actingAs($this->owner)->post(route('dapur.documents.photos.store', $tt), ['photos' => $files])
        ->assertSessionHasErrors('photos');

    expect($tt->photos()->count())->toBe(0);
});

it('B5: keeps the documents when PDF rendering fails and renders on download', function () {
    $order = makeOrder($this->dapur, ['delivery_date' => '2030-01-15']);
    makeOrderItem($order, $this->wet, $this->supplier);

    Pdf::shouldReceive('loadView')->once()->andThrow(new RuntimeException('pdf rusak'));
    $document = $this->documents->generateDocuments($this->dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', $this->ops->id)['documents']->first();

    expect($document->fresh()->pdf_url)->toBeNull();
    app()->forgetInstance('dompdf.wrapper');
    Pdf::clearResolvedInstances();

    $this->actingAs($this->ops)->get(route('ops.delivery-notes.download', $document))->assertOk();
    expect($document->fresh()->pdf_url)->not->toBeNull();
});

it('B5: keeps a saved STT when its Tanda Terima PDF fails', function () {
    [$order, $line] = issueTt($this);

    Pdf::shouldReceive('loadView')->andThrow(new RuntimeException('pdf rusak'));

    $this->actingAs($this->owner)->post(route('dapur.orders.stt', $order), [
        'items' => [['id' => $line->id, 'qty_received' => 10, 'qty_rejected' => 0]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect((float) $line->fresh()->qty_received)->toBe(10.0);
});

it('numbers documents in sequence across Orders of one Dapur on one day', function () {
    $this->travelTo(Carbon::parse('2030-01-14 23:00:00', 'UTC'));

    [, , $first] = issueTt($this, makeOrder($this->dapur, ['delivery_date' => '2030-02-01']));
    [, , $second] = issueTt($this, makeOrder($this->dapur, ['delivery_date' => '2030-02-02']));

    expect($first->sj_number)->toBe('TT/20300115/DPR-A/01');
    expect($second->sj_number)->toBe('TT/20300115/DPR-A/02');
});

it('B6: a wet line picks up the daily price entered before its document', function () {
    $order = makeOrder($this->dapur, ['delivery_date' => '2030-01-15', 'status' => 'SUBMITTED']);
    $line = makeOrderItem($order, $this->wet, $this->supplier);

    app(PricingEngineService::class)->processDailyPrices([['item_id' => $this->wet->id, 'cost_price' => 10000]], '2030-01-15', $this->purchasing->id);
    expect($line->fresh()->final_selling_price)->toBeNull();

    $this->documents->generateDocuments($this->dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', $this->ops->id);

    expect((float) $line->fresh()->final_cost_price)->toBe(10000.0);
    expect((float) $line->fresh()->final_selling_price)->toBe(11000.0);
});

it('C1: does not expose cost or margin data to Dapur', function () {
    $order = makeOrder($this->dapur, ['delivery_date' => '2030-01-15']);
    makeOrderItem($order, $this->wet, $this->supplier, ['final_cost_price' => 900]);

    $forbidden = ['base_cost_price', 'margin_type', 'margin_value', 'final_cost_price'];

    foreach ([route('dapur.orders.create'), route('dapur.orders.show', $order), route('dapur.orders.index')] as $url) {
        $response = $this->actingAs($this->owner)->get($url)->assertOk();
        $props = json_encode($response->viewData('page')['props']);

        foreach ($forbidden as $field) {
            expect($props)->not->toContain($field);
        }
    }
});

it('C2: gives earlier delivery dates stock first across suppliers', function () {
    $otherSupplier = makeSupplier(['code' => 'OTH']);
    makeStockLot($this->gudang, $this->dry, 10, 1000);
    $earlier = makeOrderItem(makeOrder($this->dapur, ['delivery_date' => '2030-01-14']), $this->dry, $otherSupplier, ['qty_ordered' => 8]);
    $later = makeOrderItem(makeOrder($this->dapur, ['delivery_date' => '2030-01-15']), $this->dry, $this->supplier, ['qty_ordered' => 8]);

    $result = $this->documents->generateDocuments($this->dapur->id, SuratJalan::SURAT_JALAN, '2030-01-15', $this->ops->id);

    expect($result['documents'])->toHaveCount(1);
    expect($result['held_items']->pluck('id')->all())->toBe([$later->id]);
    expect($earlier->fresh()->surat_jalan_id)->toBe($result['documents']->first()->id);
});

it('does not wrongly hold a line because of float rounding', function () {
    makeStockLot($this->gudang, $this->dry, 0.3, 1000);
    $order = makeOrder($this->dapur, ['delivery_date' => '2030-01-15']);
    $a = makeOrderItem($order, $this->dry, $this->supplier, ['qty_ordered' => 0.1]);
    $b = makeOrderItem($order, $this->dry, $this->supplier, ['qty_ordered' => 0.2]);

    $result = $this->documents->generateDocuments($this->dapur->id, SuratJalan::SURAT_JALAN, '2030-01-15', $this->ops->id);

    expect($result['held_items'])->toHaveCount(0);
    expect($a->fresh()->surat_jalan_id)->not->toBeNull();
    expect($b->fresh()->surat_jalan_id)->not->toBeNull();
});

it('C4: numbers documents and notas by the Jakarta date, sequenced per Dapur and type', function () {
    // 06:00 WIB on the 15th is still the 14th in UTC.
    $this->travelTo(Carbon::parse('2030-01-14 23:00:00', 'UTC'));
    $second = makeSupplier(['code' => 'DUA']);
    $sayur = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $second->id]);
    $order = makeOrder($this->dapur, ['delivery_date' => '2030-01-15']);
    makeOrderItem($order, $this->wet, $this->supplier);
    makeOrderItem($order, $sayur, $second);

    $documents = $this->documents->generateDocuments($this->dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', $this->ops->id)['documents'];

    expect($documents->pluck('sj_number')->all())->toBe(['TT/20300115/DPR-A/01']);

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store'), [
        'supplier_id' => $this->supplier->id,
        'note_date' => '2030-01-15',
        'items' => [['item_id' => $this->dry->id, 'qty' => 1, 'unit_price' => 1000]],
    ])->assertSessionHasNoErrors();

    expect(PurchaseNote::firstOrFail()->note_number)->toBe('NOTA/20300115/01');
});

it('returns a validation error, not a 500, for array input in item_id', function () {
    $this->actingAs($this->owner)->post(route('dapur.orders.store'), [
        'delivery_date' => '2030-01-15',
        'items' => [['item_id' => [1], 'qty_ordered' => 1]],
    ])->assertSessionHasErrors('items.0.item_id');
});

it('builds the local demo dataset', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Invoice::count())->toBeGreaterThan(0);
    expect(SuratJalan::where('document_type', SuratJalan::TANDA_TERIMA)->exists())->toBeTrue();
});
