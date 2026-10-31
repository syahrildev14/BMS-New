<?php

use App\Models\Invoice;
use App\Models\SupplierBill;
use App\Models\SuratJalan;
use App\Models\User;
use App\Services\SplitInvoiceService;
use App\Services\SupplierBillService;
use Illuminate\Validation\ValidationException;

/**
 * Reproduces a real scenario found in dev data: a WET_GOODS order_item gets
 * its qty_received filled in (STT done) and shipped (surat_jalan_id set),
 * but PricingEngineService never synced final_selling_price/final_cost_price
 * for this Order's delivery_date (Purchasing hasn't entered a daily price
 * for that specific future date yet). Without a guard, the invoice/bill
 * total silently computes as (qty * null) = 0.
 */
function setUpOrderWithUnpricedWetGoodsItem(): array
{
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS']);
    $opsUser = User::factory()->create();
    $order = makeOrder($dapur, ['status' => 'LOCKED', 'delivery_date' => '2030-06-01']);

    $sj = SuratJalan::create([
        'sj_number' => 'SJ/TEST/'.uniqid(),
        'order_id' => $order->id,
        'type' => 'PRIMARY',
        'sequence_number' => 1,
        'printed_by' => $opsUser->id,
    ]);

    $orderItem = makeOrderItem($order, $item, $supplier, [
        'surat_jalan_id' => $sj->id,
        'qty_received' => 20,
        'qty_rejected' => 0,
        'final_selling_price' => null,
        'final_cost_price' => null,
    ]);

    return ['order' => $order, 'orderItem' => $orderItem];
}

it('refuses to generate an invoice with a structured error (date + item list) an admin UI can render as a proper list', function () {
    ['order' => $order] = setUpOrderWithUnpricedWetGoodsItem();

    try {
        app(SplitInvoiceService::class)->generateInvoicesForOrder($order);
        $this->fail('Expected an exception to be thrown.');
    } catch (ValidationException $e) {
        $errors = $e->errors();

        // Structured fields, not just one squished sentence — the frontend
        // needs the date and the item list separately to render a proper
        // alert with a bullet list instead of one long line of text.
        expect($errors['order_id'][0])->toContain('2030-06-01');
        expect($errors['unpriced_items'][0])->toBe('Item Test');
        expect($errors['unpriced_date'][0])->toBe('2030-06-01');
    }

    expect(Invoice::count())->toBe(0);
});

it('refuses to generate a supplier bill with a structured error (date + item list)', function () {
    ['order' => $order] = setUpOrderWithUnpricedWetGoodsItem();

    try {
        app(SupplierBillService::class)->generateBillsForOrder($order);
        $this->fail('Expected an exception to be thrown.');
    } catch (ValidationException $e) {
        $errors = $e->errors();

        expect($errors['order_id'][0])->toContain('2030-06-01');
        expect($errors['unpriced_items'][0])->toBe('Item Test');
        expect($errors['unpriced_date'][0])->toBe('2030-06-01');
    }

    expect(SupplierBill::count())->toBe(0);
});

it('generates the invoice correctly once the final_selling_price is synced', function () {
    ['order' => $order, 'orderItem' => $orderItem] = setUpOrderWithUnpricedWetGoodsItem();

    $orderItem->update(['final_selling_price' => 1000]);

    $invoices = app(SplitInvoiceService::class)->generateInvoicesForOrder($order);

    expect($invoices)->toHaveCount(1);
    expect((float) $invoices->first()->total_amount)->toBe(20000.0);
});
