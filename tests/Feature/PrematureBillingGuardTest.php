<?php

use App\Models\Invoice;
use App\Models\SupplierBill;
use App\Models\SuratJalan;
use App\Models\User;
use App\Services\SplitInvoiceService;
use App\Services\SupplierBillService;

function setUpOrderWithPartialStt(): array
{
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS']);
    $opsUser = User::factory()->create();
    $order = makeOrder($dapur, ['status' => 'LOCKED']);

    $suratJalan = SuratJalan::create([
        'sj_number' => 'SJ/TEST/'.uniqid(),
        'order_id' => $order->id,
        'type' => 'PRIMARY',
        'sequence_number' => 1,
        'printed_by' => $opsUser->id,
    ]);

    $sttDoneItem = makeOrderItem($order, $item, $supplier, [
        'surat_jalan_id' => $suratJalan->id,
        'qty_received' => 8,
        'qty_rejected' => 2,
        'final_selling_price' => 1500,
        'final_cost_price' => 1000,
    ]);

    $sttPendingItem = makeOrderItem($order, $item, $supplier, [
        'surat_jalan_id' => $suratJalan->id,
        'qty_received' => null,
        'qty_rejected' => null,
        'final_selling_price' => 1500,
        'final_cost_price' => 1000,
    ]);

    return ['order' => $order, 'sttDoneItem' => $sttDoneItem, 'sttPendingItem' => $sttPendingItem];
}

it('refuses to generate invoices while any shipped item is still missing its STT', function () {
    ['order' => $order] = setUpOrderWithPartialStt();

    expect(fn () => app(SplitInvoiceService::class)->generateInvoicesForOrder($order))
        ->toThrow(RuntimeException::class);

    expect(Invoice::count())->toBe(0);
});

it('refuses to generate supplier bills while any shipped item is still missing its STT', function () {
    ['order' => $order] = setUpOrderWithPartialStt();

    expect(fn () => app(SupplierBillService::class)->generateBillsForOrder($order))
        ->toThrow(RuntimeException::class);

    expect(SupplierBill::count())->toBe(0);
});

it('generates the invoice once every shipped item has its STT recorded', function () {
    ['order' => $order, 'sttPendingItem' => $sttPendingItem] = setUpOrderWithPartialStt();

    $sttPendingItem->update(['qty_received' => 10, 'qty_rejected' => 0]);

    $invoices = app(SplitInvoiceService::class)->generateInvoicesForOrder($order);

    expect($invoices)->toHaveCount(1);
    expect(Invoice::count())->toBe(1);
});
