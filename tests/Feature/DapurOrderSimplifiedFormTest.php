<?php

use App\Models\Gudang;
use App\Models\Order;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->travelTo(Carbon::parse('2026-11-02 10:00:00', 'Asia/Jakarta'));
});

it('has a default Gudang right after migrating, without any seeder', function () {
    expect(Gudang::count())->toBe(1);
    expect(Gudang::first()->code)->toBe('GDG-PUSAT');
});

it('lets Dapur create an order with only item + qty; supplier defaults to the item master and price comes from it', function () {
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplier->id, 'base_selling_price' => 12500]);
    $dapurUser = makeUserWithRole('dapur', ['dapur_id' => $dapur->id]);

    $response = $this->actingAs($dapurUser)->post(route('dapur.orders.store'), [
        'delivery_date' => '2030-01-15',
        'items' => [['item_id' => $item->id, 'qty_ordered' => 4]],
    ]);

    $response->assertSessionHasNoErrors();

    $orderItem = Order::where('dapur_id', $dapur->id)->firstOrFail()->orderItems()->firstOrFail();
    expect($orderItem->supplier_id)->toBe($supplier->id);
    expect((float) $orderItem->estimated_price)->toBe(12500.0);
});

it('uses the supplier Dapur picked but ignores estimated_price sent by the client', function () {
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $otherSupplier = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplier->id, 'base_selling_price' => 5000]);
    $dapurUser = makeUserWithRole('dapur', ['dapur_id' => $dapur->id]);

    $this->actingAs($dapurUser)->post(route('dapur.orders.store'), [
        'delivery_date' => '2030-01-15',
        'items' => [['item_id' => $item->id, 'qty_ordered' => 1, 'supplier_id' => $otherSupplier->id, 'estimated_price' => 1]],
    ])->assertSessionHasNoErrors();

    $orderItem = Order::where('dapur_id', $dapur->id)->firstOrFail()->orderItems()->firstOrFail();
    expect($orderItem->supplier_id)->toBe($otherSupplier->id);
    expect((float) $orderItem->estimated_price)->toBe(5000.0);
});

it('accepts an item without a default supplier when Dapur picks one', function () {
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'DRY_GOODS', 'default_supplier_id' => null]);
    $dapurUser = makeUserWithRole('dapur', ['dapur_id' => $dapur->id]);

    $this->actingAs($dapurUser)->post(route('dapur.orders.store'), [
        'delivery_date' => '2030-01-15',
        'items' => [['item_id' => $item->id, 'qty_ordered' => 2, 'supplier_id' => $supplier->id]],
    ])->assertSessionHasNoErrors();

    expect(Order::firstOrFail()->orderItems()->firstOrFail()->supplier_id)->toBe($supplier->id);
});

it('rejects an unknown supplier', function () {
    $dapur = makeDapur();
    $item = makeItem(['default_supplier_id' => makeSupplier()->id]);
    $dapurUser = makeUserWithRole('dapur', ['dapur_id' => $dapur->id]);

    $this->actingAs($dapurUser)->post(route('dapur.orders.store'), [
        'delivery_date' => '2030-01-15',
        'items' => [['item_id' => $item->id, 'qty_ordered' => 2, 'supplier_id' => 999999]],
    ])->assertSessionHasErrors('items.0.supplier_id');

    expect(Order::count())->toBe(0);
});

it('rejects an item without a default supplier and creates no order', function () {
    $dapur = makeDapur();
    $item = makeItem(['name' => 'Gula Pasir', 'category' => 'DRY_GOODS', 'default_supplier_id' => null]);
    $dapurUser = makeUserWithRole('dapur', ['dapur_id' => $dapur->id]);

    $response = $this->actingAs($dapurUser)->post(route('dapur.orders.store'), [
        'delivery_date' => '2030-01-15',
        'items' => [['item_id' => $item->id, 'qty_ordered' => 2]],
    ]);

    $response->assertSessionHasErrors('items.0.item_id');
    expect(session('errors')->first('items.0.item_id'))->toContain('Gula Pasir');
    expect(Order::count())->toBe(0);
});

it('rejects an inactive item', function () {
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['is_active' => false, 'default_supplier_id' => $supplier->id]);
    $dapurUser = makeUserWithRole('dapur', ['dapur_id' => $dapur->id]);

    $this->actingAs($dapurUser)->post(route('dapur.orders.store'), [
        'delivery_date' => '2030-01-15',
        'items' => [['item_id' => $item->id, 'qty_ordered' => 2]],
    ])->assertSessionHasErrors('items.0.item_id');

    expect(Order::count())->toBe(0);
});

it('lets Dapur add items to an existing order with only item + qty', function () {
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplier->id, 'base_selling_price' => 3000]);
    $dapurUser = makeUserWithRole('dapur', ['dapur_id' => $dapur->id]);
    $order = makeOrder($dapur, ['status' => 'SUBMITTED', 'delivery_date' => '2030-01-15']);

    $this->actingAs($dapurUser)->post(route('dapur.orders.items.store', $order), [
        'items' => [['item_id' => $item->id, 'qty_ordered' => 6]],
    ])->assertSessionHasNoErrors();

    $orderItem = $order->orderItems()->firstOrFail();
    expect($orderItem->supplier_id)->toBe($supplier->id);
    expect((float) $orderItem->estimated_price)->toBe(3000.0);
});

it('rejects an add-on item without a default supplier', function () {
    $dapur = makeDapur();
    $item = makeItem(['default_supplier_id' => null]);
    $dapurUser = makeUserWithRole('dapur', ['dapur_id' => $dapur->id]);
    $order = makeOrder($dapur, ['status' => 'SUBMITTED', 'delivery_date' => '2030-01-15']);

    $this->actingAs($dapurUser)->post(route('dapur.orders.items.store', $order), [
        'items' => [['item_id' => $item->id, 'qty_ordered' => 6]],
    ])->assertSessionHasErrors('items.0.item_id');

    expect($order->orderItems()->count())->toBe(0);
});
