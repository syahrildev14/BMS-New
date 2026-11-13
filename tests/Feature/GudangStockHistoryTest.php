<?php

use App\Models\OrderItem;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\RoleAndPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->withoutVite();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('shows in/out totals per item and a merged history with running balance', function () {
    $gudang = makeGudang();
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'DRY_GOODS', 'default_supplier_id' => $supplier->id]);
    $stock = app(StockService::class);

    // Masuk: 2 lot (total 10), via opname surplus so a real movement is written.
    $this->travelTo(now()->subMinutes(10));
    $stock->recordOpname($gudang, $item, 4, $this->admin);
    $this->travelTo(now()->addMinute());
    $stock->recordOpname($gudang, $item, 10, $this->admin);

    // Keluar: 7 for one order line — spans both lots, so 2 movement rows that must merge into 1.
    $this->travelTo(now()->addMinute());
    $orderItem = makeOrderItem(makeOrder($dapur), $item, $supplier, ['qty_ordered' => 7]);
    $stock->consumeFifo($gudang, $item, 7, OrderItem::class, $orderItem->id, $this->admin);

    // Opname kekurangan: fisik 2 dari sistem 3.
    $this->travelTo(now()->addMinute());
    $stock->recordOpname($gudang, $item, 2, $this->admin);
    $this->travelBack();

    $this->actingAs($this->admin)
        ->get(route('gudang.stock.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('gudang/stock/index')
            ->where('items.0.qty_in', 10)
            ->where('items.0.qty_out', 8)
            ->where('items.0.qty_on_hand', 2));

    $this->actingAs($this->admin)
        ->get(route('gudang.stock.history', $item))
        ->assertInertia(fn (Assert $page) => $page
            ->component('gudang/stock/history')
            ->has('movements', 4)
            // Terbaru di atas.
            ->where('movements.0.type', 'OPNAME_ADJUST')
            ->where('movements.0.qty_out', 1)
            ->where('movements.0.balance', 2)
            ->where('movements.1.type', 'ORDER_OUT')
            ->where('movements.1.qty_out', 7)
            ->where('movements.1.balance', 3)
            ->where('movements.1.party', $dapur->name)
            ->where('movements.2.qty_in', 6)
            ->where('movements.2.balance', 10)
            ->where('movements.3.qty_in', 4)
            ->where('movements.3.balance', 4));
});
