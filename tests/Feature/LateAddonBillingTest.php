<?php

use App\Models\Invoice;
use App\Models\SuratJalan;
use App\Models\User;
use App\Services\SplitInvoiceService;

it('does not silently drop a late add-on shipment STT-ed after its invoice was already issued', function () {
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS']);
    $opsUser = User::factory()->create();
    $order = makeOrder($dapur, ['status' => 'LOCKED']);

    $primarySj = SuratJalan::create([
        'sj_number' => 'SJ/TEST/PRIMARY-'.uniqid(),
        'order_id' => $order->id,
        'type' => 'PRIMARY',
        'sequence_number' => 1,
        'printed_by' => $opsUser->id,
    ]);

    $primaryItem = makeOrderItem($order, $item, $supplier, [
        'surat_jalan_id' => $primarySj->id,
        'qty_received' => 10,
        'qty_rejected' => 0,
        'final_selling_price' => 1000,
    ]);

    $service = app(SplitInvoiceService::class);
    $invoices = $service->generateInvoicesForOrder($order);
    $invoice = $invoices->first();
    $service->approveInvoice($invoice);

    $invoice->refresh();
    expect($invoice->status)->toBe('ISSUED');
    expect((float) $invoice->total_amount)->toBe(10000.0);

    // A day later: Ops ships an add-on SJ for the same Order+Supplier, Dapur
    // STTs it — this is a normal, PRD-sanctioned flow (Module 3 add-on SJ),
    // which can legitimately happen after the first invoice was approved.
    $addonSj = SuratJalan::create([
        'sj_number' => 'SJ-ADD/TEST/ADDON-'.uniqid(),
        'order_id' => $order->id,
        'type' => 'ADDON',
        'sequence_number' => 2,
        'printed_by' => $opsUser->id,
    ]);

    makeOrderItem($order, $item, $supplier, [
        'surat_jalan_id' => $addonSj->id,
        'qty_received' => 4,
        'qty_rejected' => 0,
        'final_selling_price' => 1000,
    ]);

    // Regenerating must NOT silently leave the add-on's 4,000 unbilled: it
    // goes onto the same invoice, which needs the Checker's approval again.
    $service->generateInvoicesForOrder($order);

    expect(Invoice::count())->toBe(1);
    $invoice->refresh();
    expect($invoice->status)->toBe('DRAFT');
    expect((float) $invoice->total_amount)->toBe(14000.0);
});
