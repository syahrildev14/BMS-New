<?php

use App\Models\Order;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;

it('enforces one Order per dapur per delivery date at the database level', function () {
    $dapur = makeDapur();
    makeOrder($dapur, ['delivery_date' => '2030-05-01']);

    expect(fn () => makeOrder($dapur, ['delivery_date' => '2030-05-01']))
        ->toThrow(QueryException::class);
});

it('reuses the existing Order instead of crashing when a concurrent submission already created it for the same dapur+date', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    makeGudang();
    $dapur = makeDapur();
    $user = makeUserWithRole('dapur', ['dapur_id' => $dapur->id]);
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS']);

    $deliveryDate = now()->addDays(2)->toDateString();

    // Simulate "another request for this exact dapur+date already won the
    // race": the Order already exists by the time this request reaches
    // findOrCreateOrderSafely().
    $existingOrder = makeOrder($dapur, ['delivery_date' => $deliveryDate, 'status' => 'DRAFT']);

    $this->withoutExceptionHandling();

    $response = $this->actingAs($user)->post(route('dapur.orders.store'), [
        'delivery_date' => $deliveryDate,
        'items' => [
            ['item_id' => $item->id, 'supplier_id' => $supplier->id, 'qty_ordered' => 1, 'estimated_price' => 1000],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('dapur.orders.index'));

    $matchingOrders = Order::where('dapur_id', $dapur->id)->where('delivery_date', $deliveryDate)->get();

    expect($matchingOrders)->toHaveCount(1);
    expect($matchingOrders->first()->id)->toBe($existingOrder->id);
    expect($existingOrder->orderItems()->count())->toBe(1);
});
