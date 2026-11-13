<?php

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\PurchaseOrderService;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->withoutVite();

    $this->purchasing = User::factory()->create();
    $this->purchasing->assignRole('purchasing');

    $this->supplier = makeSupplier();
    $this->item = makeItem(['category' => 'DRY_GOODS', 'default_supplier_id' => $this->supplier->id]);
    $this->gudang = makeGudang();
    $this->order = makeOrder(makeDapur());
});

function draftPo(): PurchaseOrder
{
    return app(PurchaseOrderService::class)->generateDraftsForShortfall(
        test()->order,
        test()->gudang,
        collect([['item_id' => test()->item->id, 'supplier_id' => test()->supplier->id, 'qty' => 5, 'unit_cost' => 1000]]),
        test()->purchasing,
    )->first();
}

function updatePayload(PurchaseOrder $po, string $number): array
{
    return [
        'po_number' => $number,
        'items' => $po->items->map(fn ($l) => ['id' => $l->id, 'qty_ordered' => 5, 'unit_cost' => 1000])->all(),
    ];
}

it('lets Purchasing replace the auto PO number while the PO is a draft', function () {
    $po = draftPo();

    $this->actingAs($this->purchasing)
        ->patch(route('purchasing.purchase-orders.update', $po), updatePayload($po, '  PO-MANUAL-001  '))
        ->assertSessionHasNoErrors();

    expect($po->fresh()->po_number)->toBe('PO-MANUAL-001');
});

it('rejects an empty or already-used PO number', function () {
    $first = draftPo();
    $second = draftPo();

    $this->actingAs($this->purchasing)
        ->patch(route('purchasing.purchase-orders.update', $second), updatePayload($second, $first->po_number))
        ->assertSessionHasErrors(['po_number' => 'Nomor PO sudah dipakai PO lain.']);

    $this->actingAs($this->purchasing)
        ->patch(route('purchasing.purchase-orders.update', $second), updatePayload($second, '   '))
        ->assertSessionHasErrors('po_number');
});

it('skips past a manually taken number when auto-numbering the next draft', function () {
    $first = draftPo(); // .../01
    $nextAuto = preg_replace('/\d+$/', '02', $first->po_number);

    // Nomor manual "mengambil" nomor urut otomatis berikutnya.
    $first->update(['po_number' => $nextAuto]);

    $second = draftPo();

    expect($second->po_number)->toBe(preg_replace('/\d+$/', '03', $first->po_number));
});
