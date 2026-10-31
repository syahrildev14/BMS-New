<?php

use App\Models\SuratJalan;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

function setUpShippedOrderItem(float $qtyOrdered = 10): array
{
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS']);
    $opsUser = User::factory()->create();
    $dapurUser = makeUserWithRole('dapur', ['dapur_id' => $dapur->id]);

    $order = makeOrder($dapur, ['status' => 'LOCKED']);

    $suratJalan = SuratJalan::create([
        'sj_number' => 'SJ/TEST/'.uniqid(),
        'order_id' => $order->id,
        'type' => 'PRIMARY',
        'sequence_number' => 1,
        'printed_by' => $opsUser->id,
    ]);

    $orderItem = makeOrderItem($order, $item, $supplier, [
        'qty_ordered' => $qtyOrdered,
        'surat_jalan_id' => $suratJalan->id,
    ]);

    return ['dapurUser' => $dapurUser, 'order' => $order, 'orderItem' => $orderItem];
}

it('rejects an STT submission where qty_rejected exceeds qty_received', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    ['dapurUser' => $dapurUser, 'order' => $order, 'orderItem' => $orderItem] = setUpShippedOrderItem(qtyOrdered: 10);

    $response = $this->actingAs($dapurUser)->post(route('dapur.orders.stt', $order), [
        'items' => [
            ['id' => $orderItem->id, 'qty_received' => 10, 'qty_rejected' => 15],
        ],
    ]);

    $response->assertSessionHasErrors();

    $orderItem->refresh();
    expect($orderItem->qty_received)->toBeNull();
});

it('rejects an STT submission where qty_received exceeds qty_ordered', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    ['dapurUser' => $dapurUser, 'order' => $order, 'orderItem' => $orderItem] = setUpShippedOrderItem(qtyOrdered: 10);

    $response = $this->actingAs($dapurUser)->post(route('dapur.orders.stt', $order), [
        'items' => [
            ['id' => $orderItem->id, 'qty_received' => 9999, 'qty_rejected' => 0],
        ],
    ]);

    $response->assertSessionHasErrors();

    $orderItem->refresh();
    expect($orderItem->qty_received)->toBeNull();
});

it('accepts a valid STT submission within bounds', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    ['dapurUser' => $dapurUser, 'order' => $order, 'orderItem' => $orderItem] = setUpShippedOrderItem(qtyOrdered: 10);

    $response = $this->actingAs($dapurUser)->post(route('dapur.orders.stt', $order), [
        'items' => [
            ['id' => $orderItem->id, 'qty_received' => 8, 'qty_rejected' => 2],
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $orderItem->refresh();
    expect((float) $orderItem->qty_received)->toBe(8.0);
    expect((float) $orderItem->qty_rejected)->toBe(2.0);
});
