<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/**
 * Semua module Master ini punya pola SPA identik: satu halaman index yang
 * menangani list + create + edit + delete lewat Dialog (tidak ada halaman
 * create/edit terpisah). Jadi cukup satu GET route per module.
 *
 * key   = segment URL, HARUS sama persis dengan nama folder di resources/js/pages/Master/{key}
 * value = nama page Inertia (case-sensitive, harus match nama file .tsx)
 */
$masterModules = [
    'barang'   => 'Master/Barang/index',
    'supplier' => 'Master/Supplier/index',
    'gudang'   => 'Master/Gudang/index',
    'satuan'   => 'Master/Satuan/index',
    'armada'   => 'Master/Armada/index',
    'sppg'     => 'Master/Sppg/index',
];

foreach ($masterModules as $slug => $page) {
    Route::get("/master/{$slug}", fn () => Inertia::render($page))
        ->name("master.{$slug}");
}
