<?php

use App\Models\SuratJalan;
use App\Models\User;
use App\Services\SplitInvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;

it('still marks the invoice ISSUED even when PDF rendering fails, instead of a 500', function () {
    $dapur = makeDapur();
    $supplier = makeSupplier();
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

    makeOrderItem($order, $item, $supplier, [
        'surat_jalan_id' => $sj->id, 'qty_received' => 5, 'qty_rejected' => 0, 'final_selling_price' => 1000,
    ]);

    $service = app(SplitInvoiceService::class);
    $invoice = $service->generateInvoicesForOrder($order)->first();

    Pdf::shouldReceive('loadView')->andThrow(new Exception('dompdf boom'));

    $approved = $service->approveInvoice($invoice);

    expect($approved->status)->toBe('ISSUED');
    expect($approved->pdf_url)->toBeNull();
});
