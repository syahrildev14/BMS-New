<?php

use App\Models\SuratJalan;
use App\Models\User;
use App\Services\SplitInvoiceService;
use Database\Seeders\RoleAndPermissionSeeder;
use Spatie\Permission\Models\Role;

function setUpIssuedInvoice(): array
{
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

    return ['invoice' => $invoice->refresh()];
}

it('does not let a user with only invoices.manage mark an invoice as paid', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    ['invoice' => $invoice] = setUpIssuedInvoice();

    $makerOnlyRole = Role::findOrCreate('invoice-maker-only', 'web');
    $makerOnlyRole->syncPermissions(['invoices.manage']);
    $user = User::factory()->create();
    $user->assignRole('invoice-maker-only');

    $response = $this->actingAs($user)->post(route('finance.invoices.pay', $invoice), [
        'payment_method' => 'transfer',
    ]);

    $response->assertForbidden();
    expect($invoice->refresh()->status)->toBe('ISSUED');
});

it('lets a user with invoices.approve mark an invoice as paid', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    ['invoice' => $invoice] = setUpIssuedInvoice();

    $checkerRole = Role::findOrCreate('invoice-checker', 'web');
    $checkerRole->syncPermissions(['invoices.approve']);
    $user = User::factory()->create();
    $user->assignRole('invoice-checker');

    $response = $this->actingAs($user)->post(route('finance.invoices.pay', $invoice), [
        'payment_method' => 'transfer',
    ]);

    $response->assertRedirect();
    expect($invoice->refresh()->status)->toBe('PAID');
});
