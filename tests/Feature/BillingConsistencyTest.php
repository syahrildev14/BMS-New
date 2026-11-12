<?php

use App\Models\DailyPrice;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PurchaseNote;
use App\Models\SupplierBill;
use App\Models\SuratJalan;
use App\Services\DeliveryNoteService;
use App\Services\DeliveryVerificationService;
use App\Services\PricingEngineService;
use App\Services\SplitInvoiceService;
use App\Services\SupplierBillService;
use App\Support\Format;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RoleAndPermissionSeeder::class);
    $this->gudang = makeGudang();
    $this->dapur = makeDapur();
    $this->owner = makeUserWithRole('dapur', ['dapur_id' => $this->dapur->id]);
    $this->ops = makeUserWithRole('ops');
    $this->purchasing = makeUserWithRole('purchasing');
    $this->supplier = makeSupplier();
    $this->wet = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $this->supplier->id]);
    $this->order = makeOrder($this->dapur, ['delivery_date' => '2030-01-15', 'status' => 'LOCKED']);
    $this->invoices = app(SplitInvoiceService::class);
    $this->bills = app(SupplierBillService::class);
});

function billingDocument($test, string $status = SuratJalan::VERIFICATION_VERIFIED, string $type = 'PRIMARY'): SuratJalan
{
    return SuratJalan::create([
        'sj_number' => 'TT/'.uniqid(),
        'document_type' => SuratJalan::TANDA_TERIMA,
        'dapur_id' => $test->dapur->id,
        'supplier_id' => $test->supplier->id,
        'shipment_date' => '2030-01-15',
        'type' => $type,
        'printed_by' => $test->ops->id,
        'verification_status' => $status,
    ]);
}

function billingLine($test, SuratJalan $document, float $qty, float $sellingPrice = 1000): OrderItem
{
    return makeOrderItem($test->order, $test->wet, $test->supplier, [
        'surat_jalan_id' => $document->id,
        'qty_ordered' => $qty,
        'qty_received' => $qty,
        'qty_rejected' => 0,
        'final_selling_price' => $sellingPrice,
        'final_cost_price' => $sellingPrice - 100,
    ]);
}

it('revises the issued invoice for a late shipment and needs a new approval', function () {
    billingLine($this, billingDocument($this), 10);
    $invoice = $this->invoices->approveInvoice($this->invoices->generateInvoicesForOrder($this->order)->first());
    expect($invoice->pdf_url)->not->toBeNull();

    billingLine($this, billingDocument($this, type: 'ADDON'), 4);
    $this->invoices->generateInvoicesForOrder($this->order);

    $revised = $invoice->fresh();
    expect(Invoice::count())->toBe(1);
    expect($revised->invoice_number)->toBe($invoice->invoice_number);
    expect($revised->status)->toBe('DRAFT');
    expect((float) $revised->total_amount)->toBe(14000.0);
    expect($revised->pdf_url)->toBeNull();

    $this->invoices->approveInvoice($revised);

    expect($revised->fresh()->status)->toBe('ISSUED');
    expect($this->order->fresh()->status)->toBe('COMPLETED');
});

it('revises an overdue invoice for a late shipment', function () {
    billingLine($this, billingDocument($this), 10);
    $invoice = $this->invoices->approveInvoice($this->invoices->generateInvoicesForOrder($this->order)->first());
    $invoice->update(['status' => 'OVERDUE']);
    billingLine($this, billingDocument($this, type: 'ADDON'), 4);

    $this->invoices->generateInvoicesForOrder($this->order);

    expect($invoice->fresh()->status)->toBe('DRAFT');
    expect((float) $invoice->fresh()->total_amount)->toBe(14000.0);
});

it('bills a late shipment on an additional invoice when the invoice is already paid', function () {
    billingLine($this, billingDocument($this), 10);
    $paid = $this->invoices->approveInvoice($this->invoices->generateInvoicesForOrder($this->order)->first());
    $paid->update(['status' => 'PAID', 'paid_at' => now(), 'payment_method' => 'TRANSFER']);
    billingLine($this, billingDocument($this, type: 'ADDON'), 4);

    $additional = $this->invoices->generateInvoicesForOrder($this->order)->first();

    expect(Invoice::count())->toBe(2);
    expect($additional->id)->not->toBe($paid->id);
    expect($additional->invoice_number)->not->toBe($paid->invoice_number);
    expect($additional->status)->toBe('DRAFT');
    expect((float) $additional->total_amount)->toBe(4000.0);
    expect($paid->fresh()->status)->toBe('PAID');
    expect((float) $paid->fresh()->total_amount)->toBe(10000.0);
    expect($paid->fresh()->payment_method)->toBe('TRANSFER');

    // Generating again reuses the same additional draft.
    expect($this->invoices->generateInvoicesForOrder($this->order)->first()->id)->toBe($additional->id);
    expect(Invoice::count())->toBe(2);

    $this->invoices->approveInvoice($additional);

    expect($additional->fresh()->status)->toBe('ISSUED');
    expect($this->order->fresh()->status)->toBe('COMPLETED');
});

it('adds a further late shipment to an unpaid additional invoice', function () {
    billingLine($this, billingDocument($this), 10);
    $paid = $this->invoices->approveInvoice($this->invoices->generateInvoicesForOrder($this->order)->first());
    $paid->update(['status' => 'PAID']);
    billingLine($this, billingDocument($this, type: 'ADDON'), 4);
    $additional = $this->invoices->approveInvoice($this->invoices->generateInvoicesForOrder($this->order)->first());

    billingLine($this, billingDocument($this, type: 'ADDON'), 1);
    $this->invoices->generateInvoicesForOrder($this->order);

    expect(Invoice::count())->toBe(2);
    expect($additional->fresh()->status)->toBe('DRAFT');
    expect((float) $additional->fresh()->total_amount)->toBe(5000.0);
});

it('leaves a fully paid order alone and refuses when paid exceeds what is billable', function () {
    $line = billingLine($this, billingDocument($this), 10);
    $paid = $this->invoices->approveInvoice($this->invoices->generateInvoicesForOrder($this->order)->first());
    $paid->update(['status' => 'PAID']);

    expect($this->invoices->generateInvoicesForOrder($this->order)->first()->id)->toBe($paid->id);
    expect(Invoice::count())->toBe(1);

    $line->update(['qty_received' => 9]);

    expect(fn () => $this->invoices->generateInvoicesForOrder($this->order))->toThrow(HttpException::class);
    expect(Invoice::count())->toBe(1);
});

it('bills a late shipment on an additional supplier bill when the bill is already paid', function () {
    billingLine($this, billingDocument($this), 10);
    $paid = $this->bills->approveBill($this->bills->generateBillsForOrder($this->order)->first());
    $paid->update(['status' => 'PAID']);
    billingLine($this, billingDocument($this, type: 'ADDON'), 4);

    $additional = $this->bills->generateBillsForOrder($this->order)->first();

    expect(SupplierBill::count())->toBe(2);
    expect($additional->id)->not->toBe($paid->id);
    expect((float) $additional->total_amount)->toBe(3600.0);
    expect($this->bills->approveBill($additional)->status)->toBe('ISSUED');
    expect($paid->fresh()->status)->toBe('PAID');
});

it('still protects lines covered by a paid invoice and its additional invoice', function () {
    $first = billingDocument($this);
    $line = billingLine($this, $first, 10);
    $paid = $this->invoices->approveInvoice($this->invoices->generateInvoicesForOrder($this->order)->first());
    $paid->update(['status' => 'PAID']);
    $addon = billingDocument($this, type: 'ADDON');
    $addonLine = billingLine($this, $addon, 4);
    $this->invoices->approveInvoice($this->invoices->generateInvoicesForOrder($this->order)->first());

    foreach ([[$first, $line, 10], [$addon, $addonLine, 4]] as [$document, $orderItem, $qty]) {
        $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$document, $document->orderItems()->value('supplier_id')]), [
            'items' => [['order_item_id' => $orderItem->id, 'qty' => $qty, 'unit_price' => 1]],
        ])->assertSessionHasErrors('note');
    }
});

it('computes PDF line subtotals from the quantity that passed QC', function () {
    $html = null;
    Pdf::shouldReceive('loadView')->andReturnUsing(function (string $view, array $data) use (&$html) {
        $html = view($view, $data)->render();

        return new class
        {
            public function output(): string
            {
                return 'pdf';
            }
        };
    });

    $document = billingDocument($this);
    billingLine($this, $document, 10)->update(['qty_rejected' => 2]);
    makeOrderItem($this->order, $this->wet, $this->supplier, [
        'surat_jalan_id' => $document->id, 'qty_ordered' => 5, 'qty_received' => 0, 'qty_rejected' => 0,
    ]);

    $this->invoices->approveInvoice($this->invoices->generateInvoicesForOrder($this->order)->first());

    expect($html)->toContain(Format::rupiah(8000));
    expect($html)->not->toContain(Format::rupiah(10000));
});

it('shows what is already paid on the additional invoice PDF', function () {
    $html = null;
    Pdf::shouldReceive('loadView')->andReturnUsing(function (string $view, array $data) use (&$html) {
        $html = view($view, $data)->render();

        return new class
        {
            public function output(): string
            {
                return 'pdf';
            }
        };
    });

    billingLine($this, billingDocument($this), 10);
    $paid = $this->invoices->approveInvoice($this->invoices->generateInvoicesForOrder($this->order)->first());
    expect($html)->not->toContain('Sudah Dibayar');

    $paid->update(['status' => 'PAID']);
    billingLine($this, billingDocument($this, type: 'ADDON'), 4);
    $this->invoices->approveInvoice($this->invoices->generateInvoicesForOrder($this->order)->first());

    expect($html)->toContain('Sudah Dibayar');
    expect($html)->toContain($paid->invoice_number);
});

it('revises the issued supplier bill for a late shipment', function () {
    billingLine($this, billingDocument($this), 10);
    $bill = $this->bills->approveBill($this->bills->generateBillsForOrder($this->order)->first());

    billingLine($this, billingDocument($this, type: 'ADDON'), 4);
    $this->bills->generateBillsForOrder($this->order);

    expect(SupplierBill::count())->toBe(1);
    expect($bill->fresh()->bill_number)->toBe($bill->bill_number);
    expect($bill->fresh()->status)->toBe('DRAFT');
    expect((float) $bill->fresh()->total_amount)->toBe(12600.0);
});

it('refuses to approve a stale draft invoice or bill', function () {
    billingLine($this, billingDocument($this), 10);
    $invoice = $this->invoices->generateInvoicesForOrder($this->order)->first();
    $bill = $this->bills->generateBillsForOrder($this->order)->first();

    billingLine($this, billingDocument($this, type: 'ADDON'), 4);

    expect(fn () => $this->invoices->approveInvoice($invoice))->toThrow(ValidationException::class);
    expect(fn () => $this->bills->approveBill($bill))->toThrow(ValidationException::class);
    expect($invoice->fresh()->status)->toBe('DRAFT');
    expect($bill->fresh()->status)->toBe('DRAFT');
});

it('requires the crosscheck before a supplier bill is generated or approved', function () {
    $document = billingDocument($this, SuratJalan::VERIFICATION_PENDING);
    billingLine($this, $document, 10);

    expect(fn () => $this->bills->generateBillsForOrder($this->order))->toThrow(ValidationException::class);

    $document->update(['verification_status' => SuratJalan::VERIFICATION_VERIFIED]);
    $bill = $this->bills->generateBillsForOrder($this->order)->first();
    $document->update(['verification_status' => SuratJalan::VERIFICATION_DISCREPANCY]);

    expect(fn () => $this->bills->approveBill($bill))->toThrow(ValidationException::class);
    expect($bill->fresh()->status)->toBe('DRAFT');
});

it('re-reads a supplier bill before approving it', function () {
    billingLine($this, billingDocument($this), 10);
    $stale = $this->bills->generateBillsForOrder($this->order)->first();
    SupplierBill::whereKey($stale->id)->update(['status' => 'VOID']);

    expect(fn () => $this->bills->approveBill($stale))->toThrow(HttpException::class);
    expect($stale->fresh()->status)->toBe('VOID');
});

it('keeps prices of billed lines when a manual daily price is entered', function () {
    $line = billingLine($this, billingDocument($this), 10);
    $this->invoices->approveInvoice($this->invoices->generateInvoicesForOrder($this->order)->first());

    app(PricingEngineService::class)->processDailyPrices([['item_id' => $this->wet->id, 'cost_price' => 2000]], '2030-01-15', $this->purchasing->id);

    expect((float) $line->fresh()->final_selling_price)->toBe(1000.0);
    expect((float) $this->wet->fresh()->base_cost_price)->toBe(2000.0);
});

it('voids a draft invoice only when a manual daily price really changes its lines', function () {
    $line = billingLine($this, billingDocument($this), 10, 2200);
    $line->update(['final_cost_price' => 2000]);
    $invoice = $this->invoices->generateInvoicesForOrder($this->order)->first();
    $pricing = app(PricingEngineService::class);

    $pricing->processDailyPrices([['item_id' => $this->wet->id, 'cost_price' => 2000]], '2030-01-15', $this->purchasing->id);
    expect($invoice->fresh()->status)->toBe('DRAFT');

    $pricing->processDailyPrices([['item_id' => $this->wet->id, 'cost_price' => 3000]], '2030-01-15', $this->purchasing->id);
    expect($invoice->fresh()->status)->toBe('VOID');
    expect((float) $line->fresh()->final_selling_price)->toBe(3300.0);
});

it('accepts a nota for add-on lines the issued invoice does not cover, then bills them on it', function () {
    billingLine($this, billingDocument($this), 10);
    $invoice = $this->invoices->approveInvoice($this->invoices->generateInvoicesForOrder($this->order)->first());

    $addon = billingDocument($this, SuratJalan::VERIFICATION_PENDING, 'ADDON');
    $line = makeOrderItem($this->order, $this->wet, $this->supplier, ['surat_jalan_id' => $addon->id, 'qty_ordered' => 4]);

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$addon, $addon->orderItems()->value('supplier_id')]), [
        'items' => [['order_item_id' => $line->id, 'qty' => 4, 'unit_price' => 1000]],
    ])->assertSessionHasNoErrors();

    expect((float) $line->fresh()->final_selling_price)->toBe(1100.0);

    $line->update(['qty_received' => 4, 'qty_rejected' => 0]);
    app(DeliveryVerificationService::class)->verify($addon, $this->ops);
    $this->invoices->generateInvoicesForOrder($this->order);

    expect((float) $invoice->fresh()->total_amount)->toBe(14400.0);
});

it('still refuses a nota for lines the issued invoice covers', function () {
    $document = billingDocument($this);
    $line = billingLine($this, $document, 10);
    $this->invoices->approveInvoice($this->invoices->generateInvoicesForOrder($this->order)->first());

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$document, $document->orderItems()->value('supplier_id')]), [
        'items' => [['order_item_id' => $line->id, 'qty' => 10, 'unit_price' => 1]],
    ])->assertSessionHasErrors('note');

    expect((float) $line->fresh()->final_selling_price)->toBe(1000.0);
});

it('keeps the price of a line the nota reports as undelivered', function () {
    $document = billingDocument($this, SuratJalan::VERIFICATION_PENDING);
    $line = billingLine($this, $document, 5);

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$document, $document->orderItems()->value('supplier_id')]), [
        'items' => [['order_item_id' => $line->id, 'qty' => 0, 'unit_price' => '']],
    ])->assertSessionHasNoErrors();

    expect((float) $line->fresh()->final_selling_price)->toBe(1000.0);
    expect((float) $line->fresh()->final_cost_price)->toBe(900.0);
});

it('does not bill a received line at zero when the nota reports it undelivered', function () {
    $document = billingDocument($this);
    $line = makeOrderItem($this->order, $this->wet, $this->supplier, [
        'surat_jalan_id' => $document->id, 'qty_ordered' => 5, 'qty_received' => 5, 'qty_rejected' => 0,
    ]);

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$document, $document->orderItems()->value('supplier_id')]), [
        'items' => [['order_item_id' => $line->id, 'qty' => 0, 'unit_price' => '']],
    ])->assertSessionHasNoErrors();

    expect(fn () => $this->invoices->generateInvoicesForOrder($this->order))->toThrow(ValidationException::class);

    app(PricingEngineService::class)->processDailyPrices([['item_id' => $this->wet->id, 'cost_price' => 2000]], '2030-01-15', $this->purchasing->id);

    expect((float) $line->fresh()->final_selling_price)->toBe(2200.0);
});

it('does not let a line with nothing received block billing for a missing price', function () {
    $document = billingDocument($this);
    billingLine($this, $document, 10);
    makeOrderItem($this->order, $this->wet, $this->supplier, [
        'surat_jalan_id' => $document->id, 'qty_ordered' => 5, 'qty_received' => 0, 'qty_rejected' => 0,
    ]);

    expect((float) $this->invoices->generateInvoicesForOrder($this->order)->first()->total_amount)->toBe(10000.0);
    expect((float) $this->bills->generateBillsForOrder($this->order)->first()->total_amount)->toBe(9000.0);
});

it('clears the nota price when a correction reports the line as undelivered', function () {
    $document = billingDocument($this, SuratJalan::VERIFICATION_PENDING);
    $line = makeOrderItem($this->order, $this->wet, $this->supplier, ['surat_jalan_id' => $document->id, 'qty_ordered' => 5]);

    $this->actingAs($this->purchasing)->post(route('purchasing.purchase-notes.store-wet', [$document, $document->orderItems()->value('supplier_id')]), [
        'items' => [['order_item_id' => $line->id, 'qty' => 5, 'unit_price' => 1000]],
    ])->assertSessionHasNoErrors();
    expect((float) $line->fresh()->final_cost_price)->toBe(1000.0);

    $this->actingAs($this->purchasing)->put(route('purchasing.purchase-notes.update', PurchaseNote::firstOrFail()), [
        'items' => [['order_item_id' => $line->id, 'qty' => 0, 'unit_price' => '']],
    ])->assertSessionHasNoErrors();

    expect($line->fresh()->final_cost_price)->toBeNull();
    expect($line->fresh()->final_selling_price)->toBeNull();
    expect(DailyPrice::where('item_id', $this->wet->id)->exists())->toBeTrue();
});

it('skips an order number that is already taken', function () {
    $today = now()->format('Ymd');
    makeOrder($this->dapur, ['delivery_date' => '2030-02-01', 'order_number' => "ORD/{$today}/{$this->dapur->id}/03"]);
    makeOrder($this->dapur, ['delivery_date' => '2030-02-02', 'order_number' => "ORD/{$today}/{$this->dapur->id}/04"]);

    $this->actingAs($this->owner)->post(route('dapur.orders.store'), [
        'delivery_date' => '2030-03-01',
        'items' => [['item_id' => $this->wet->id, 'qty_ordered' => 1]],
    ])->assertSessionHasNoErrors();

    expect(Order::whereDate('delivery_date', '2030-03-01')->firstOrFail()->order_number)
        ->toBe("ORD/{$today}/{$this->dapur->id}/05");
});

it('locks the Dapur before reading Orders when one is submitted', function () {
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $this->actingAs($this->owner)->post(route('dapur.orders.store'), [
        'delivery_date' => '2030-03-01',
        'items' => [['item_id' => $this->wet->id, 'qty_ordered' => 1]],
    ])->assertSessionHasNoErrors();

    $firstOrderQuery = collect($queries)->search(fn (string $sql) => str_contains($sql, 'from "orders"'));

    expect($firstOrderQuery)->toBeGreaterThan(0);
    expect($queries[$firstOrderQuery - 1])->toContain('from "dapurs"');
});

it('takes the Dapur lock before any other read when generating documents', function () {
    $order = makeOrder($this->dapur, ['delivery_date' => '2030-04-01']);
    makeOrderItem($order, $this->wet, $this->supplier);

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    app(DeliveryNoteService::class)->generateDocuments($this->dapur->id, SuratJalan::TANDA_TERIMA, '2030-04-01', $this->ops->id);

    expect($queries[0])->toContain('from "dapurs"');
});

it('refuses a qty edit or delete when the line was shipped after the page bound it', function (string $method) {
    $order = makeOrder($this->dapur, ['delivery_date' => '2030-05-01']);
    $line = makeOrderItem($order, $this->wet, $this->supplier, ['qty_ordered' => 10]);
    $document = billingDocument($this, SuratJalan::VERIFICATION_PENDING);

    // Ops commits a document right after route binding loaded the line.
    $shipped = false;
    OrderItem::retrieved(function (OrderItem $model) use (&$shipped, $document) {
        if (! $shipped) {
            $shipped = true;
            DB::table('order_items')->where('id', $model->id)->update(['surat_jalan_id' => $document->id]);
        }
    });

    $url = route($method === 'patch' ? 'dapur.orders.items.update' : 'dapur.orders.items.destroy', [$order, $line]);

    $this->actingAs($this->owner)->{$method}($url, ['qty_ordered' => 3])->assertStatus(422);

    $row = DB::table('order_items')->where('id', $line->id)->first();
    expect($row)->not->toBeNull();
    expect((float) $row->qty_ordered)->toBe(10.0);
})->with(['patch', 'delete']);
