<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

$warehouseModules = [
    'inbound'      => 'Warehouse/Inbound/index',
    'stok-gudang'  => 'Warehouse/StokGudang/index',
    'retur'        => 'Warehouse/Retur/index',
];

foreach ($warehouseModules as $slug => $page) {
    Route::get("/warehouse/{$slug}", fn () => Inertia::render($page))
        ->name('warehouse.'.str_replace('-', '_', $slug));
}
