<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

$pembelianModules = [
    'order-penawaran'               => 'Pembelian/OrderPenawaran/index',
    'daftar-pembelanjaan'           => 'Pembelian/DaftarPembelanjaan/index',
    'daftar-pembelanjaan-supplier'  => 'Pembelian/DaftarPembelanjaanSupplier/index',
];

foreach ($pembelianModules as $slug => $page) {
    Route::get("/pembelian/{$slug}", fn () => Inertia::render($page))
        ->name('pembelian.'.str_replace('-', '_', $slug));
}

// Catatan: kalau nanti tiap tahap butuh halaman detail (mis. lihat 1 order penawaran),
// tambahkan route terpisah di sini, contoh:
// Route::get('/pembelian/order-penawaran/{id}', fn ($id) => Inertia::render('Pembelian/OrderPenawaran/show', ['id' => $id]))
//     ->name('pembelian.order_penawaran.show');
