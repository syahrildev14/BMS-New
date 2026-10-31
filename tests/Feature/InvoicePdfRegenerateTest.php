<?php

use App\Models\SuratJalan;
use App\Models\User;
use App\Services\SplitInvoiceService;
use Database\Seeders\RoleAndPermissionSeeder;

it('lets Finance regenerate the PDF for an already-ISSUED invoice', function () {
    $this->seed(RoleAndPermissionSeeder::class);

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
    $service->approveInvoice($invoice);

    $invoice->update(['pdf_url' => null]); // simulate the earlier render having failed

    $financeUser = makeUserWithRole('finance');

    $response = $this->actingAs($financeUser)->post(route('finance.invoices.regenerate-pdf', $invoice));

    $response->assertRedirect();
    expect($invoice->refresh()->pdf_url)->not->toBeNull();
});
