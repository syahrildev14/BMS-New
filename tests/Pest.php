<?php

use App\Models\Dapur;
use App\Models\Gudang;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Satuan;
use App\Models\StockLot;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function makeDapur(array $attrs = []): Dapur
{
    return Dapur::create(array_merge([
        'code' => 'DPR-'.Str::random(6),
        'name' => 'Dapur Test',
    ], $attrs));
}

function makeSupplier(array $attrs = []): Supplier
{
    return Supplier::create(array_merge([
        'code' => 'SUP-'.Str::random(6),
        'name' => 'Supplier Test',
    ], $attrs));
}

function makeSatuan(array $attrs = []): Satuan
{
    return Satuan::create(array_merge([
        'code' => 'SAT-'.Str::random(6),
        'name' => 'Satuan Test',
    ], $attrs));
}

function makeGudang(array $attrs = []): Gudang
{
    return Gudang::create(array_merge([
        'code' => 'GDG-'.Str::random(6),
        'name' => 'Gudang Test',
    ], $attrs));
}

function makeItem(array $attrs = []): Item
{
    return Item::create(array_merge([
        'sku' => 'SKU-'.Str::random(8),
        'name' => 'Item Test',
        'category' => 'DRY_GOODS',
        'satuan_id' => $attrs['satuan_id'] ?? makeSatuan()->id,
        'margin_type' => 'PERCENTAGE',
        'margin_value' => 10,
        'base_cost_price' => 1000,
        'base_selling_price' => 1100,
        'is_active' => true,
    ], $attrs));
}

function makeOrder(Dapur $dapur, array $attrs = []): Order
{
    return Order::create(array_merge([
        'order_number' => 'ORD-'.Str::random(10),
        'dapur_id' => $dapur->id,
        'delivery_date' => now()->addDay()->toDateString(),
        'status' => 'SUBMITTED',
    ], $attrs));
}

function makeOrderItem(Order $order, Item $item, Supplier $supplier, array $attrs = []): OrderItem
{
    return OrderItem::create(array_merge([
        'order_id' => $order->id,
        'item_id' => $item->id,
        'supplier_id' => $supplier->id,
        'qty_ordered' => 10,
        'estimated_price' => 1000,
    ], $attrs));
}

function makeStockLot(Gudang $gudang, Item $item, float $qty, float $unitCost, ?Carbon $receivedAt = null): StockLot
{
    return StockLot::create([
        'gudang_id' => $gudang->id,
        'item_id' => $item->id,
        'qty_received' => $qty,
        'qty_remaining' => $qty,
        'unit_cost' => $unitCost,
        'received_at' => $receivedAt ?? now(),
    ]);
}

function makeUserWithRole(string $role, array $attrs = []): User
{
    $user = User::factory()->create($attrs);
    $user->assignRole($role);

    return $user;
}
