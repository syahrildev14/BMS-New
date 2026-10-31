<?php

use App\Models\SuratJalan;
use App\Models\User;
use App\Services\SplitInvoiceService;

it('marks the Order COMPLETED once every invoice under it is ISSUED', function () {
    $dapur = makeDapur();
    $supplierA = makeSupplier();
    $supplierB = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS']);
    $opsUser = User::factory()->create();
    $order = makeOrder($dapur, ['status' => 'LOCKED']);

    $sj = SuratJalan::create([
        'sj_number' => 'SJ/TEST/'.uniqid(),
        'order_id' => $order->id,
        'type' => 'PRIMARY',
        'sequence_number' => 1,
        'printed_by' => $opsUser->id,
    ]);

    makeOrderItem($order, $item, $supplierA, [
        'surat_jalan_id' => $sj->id, 'qty_received' => 5, 'qty_rejected' => 0, 'final_selling_price' => 1000,
    ]);
    makeOrderItem($order, $item, $supplierB, [
        'surat_jalan_id' => $sj->id, 'qty_received' => 5, 'qty_rejected' => 0, 'final_selling_price' => 1000,
    ]);

    $service = app(SplitInvoiceService::class);
    $invoices = $service->generateInvoicesForOrder($order);
    expect($invoices)->toHaveCount(2);

    $service->approveInvoice($invoices[0]);
    $order->refresh();
    expect($order->status)->toBe('LOCKED');

    $service->approveInvoice($invoices[1]);
    $order->refresh();
    expect($order->status)->toBe('COMPLETED');
});
