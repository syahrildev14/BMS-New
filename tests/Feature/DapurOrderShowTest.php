<?php

use App\Models\SuratJalan;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

it('shows order detail without crashing when the order has both items and a Surat Jalan', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS']);
    $dapurUser = makeUserWithRole('dapur', ['dapur_id' => $dapur->id]);
    $opsUser = User::factory()->create();

    $order = makeOrder($dapur, ['status' => 'LOCKED']);

    $suratJalan = SuratJalan::create([
        'sj_number' => 'SJ/TEST/'.uniqid(),
        'order_id' => $order->id,
        'type' => 'PRIMARY',
        'sequence_number' => 1,
        'printed_by' => $opsUser->id,
    ]);

    makeOrderItem($order, $item, $supplier, ['surat_jalan_id' => $suratJalan->id]);

    $response = $this->actingAs($dapurUser)->get(route('dapur.orders.show', $order));

    $response->assertOk();
});
