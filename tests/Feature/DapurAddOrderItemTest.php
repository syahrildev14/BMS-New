<?php

use App\Models\AuditLog;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;

it('adds a Tier 1 item to an existing Order without needing Ops approval', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    makeGudang();
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplier->id]);
    $dapurUser = makeUserWithRole('dapur', ['dapur_id' => $dapur->id]);
    $order = makeOrder($dapur, ['status' => 'SUBMITTED', 'delivery_date' => '2030-01-15']);

    $this->travelTo(Carbon::parse('2026-11-02 10:00:00', 'Asia/Jakarta'));

    $response = $this->actingAs($dapurUser)->post(route('dapur.orders.items.store', $order), [
        'items' => [
            ['item_id' => $item->id, 'supplier_id' => $supplier->id, 'qty_ordered' => 5, 'estimated_price' => 1000],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $order->refresh();
    expect($order->orderItems)->toHaveCount(1);
    expect((float) $order->orderItems->first()->qty_ordered)->toBe(5.0);
});

it('adds a Tier 2 item that lands in the Ops approval queue instead of being confirmed immediately', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    makeGudang();
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplier->id]);
    $dapurUser = makeUserWithRole('dapur', ['dapur_id' => $dapur->id]);
    $order = makeOrder($dapur, ['status' => 'SUBMITTED', 'delivery_date' => '2030-01-15']);

    $this->travelTo(Carbon::parse('2026-11-02 19:00:00', 'Asia/Jakarta'));

    $response = $this->actingAs($dapurUser)->post(route('dapur.orders.items.store', $order), [
        'items' => [
            ['item_id' => $item->id, 'supplier_id' => $supplier->id, 'qty_ordered' => 3, 'estimated_price' => 1000],
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $pendingIds = AuditLog::pendingOrderItemIds($order->id);
    $order->refresh();
    expect($order->orderItems)->toHaveCount(1);
    expect($pendingIds)->toHaveCount(1);
});

it('refuses to add items to another dapur\'s Order', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $dapurA = makeDapur();
    $dapurB = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplier->id]);
    $dapurUserA = makeUserWithRole('dapur', ['dapur_id' => $dapurA->id]);
    $orderB = makeOrder($dapurB, ['status' => 'SUBMITTED', 'delivery_date' => '2030-01-15']);

    $response = $this->actingAs($dapurUserA)->post(route('dapur.orders.items.store', $orderB), [
        'items' => [
            ['item_id' => $item->id, 'supplier_id' => $supplier->id, 'qty_ordered' => 3, 'estimated_price' => 1000],
        ],
    ]);

    $response->assertForbidden();
});

it('refuses to add items to a CANCELLED or COMPLETED Order', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplier->id]);
    $dapurUser = makeUserWithRole('dapur', ['dapur_id' => $dapur->id]);
    $order = makeOrder($dapur, ['status' => 'CANCELLED', 'delivery_date' => '2030-01-15']);

    $response = $this->actingAs($dapurUser)->post(route('dapur.orders.items.store', $order), [
        'items' => [
            ['item_id' => $item->id, 'supplier_id' => $supplier->id, 'qty_ordered' => 3, 'estimated_price' => 1000],
        ],
    ]);

    $response->assertStatus(422);
    expect($order->orderItems()->count())->toBe(0);
});
