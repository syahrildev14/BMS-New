<?php

use App\Models\SuratJalan;
use App\Models\User;
use App\Services\DeliveryNoteService;

it('refuses to generate a Surat Jalan for a CANCELLED Order', function () {
    makeGudang();
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS']);
    $opsUser = User::factory()->create();

    $order = makeOrder($dapur, ['status' => 'CANCELLED']);
    makeOrderItem($order, $item, $supplier);

    expect(fn () => app(DeliveryNoteService::class)->generateSuratJalan($order->id, $opsUser->id))
        ->toThrow(RuntimeException::class);

    expect(SuratJalan::where('order_id', $order->id)->count())->toBe(0);
});

it('refuses to generate a Surat Jalan for a COMPLETED Order', function () {
    makeGudang();
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS']);
    $opsUser = User::factory()->create();

    $order = makeOrder($dapur, ['status' => 'COMPLETED']);
    makeOrderItem($order, $item, $supplier);

    expect(fn () => app(DeliveryNoteService::class)->generateSuratJalan($order->id, $opsUser->id))
        ->toThrow(RuntimeException::class);

    expect(SuratJalan::where('order_id', $order->id)->count())->toBe(0);
});
