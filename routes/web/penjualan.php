<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/penjualan', fn () => Inertia::render('Penjualan/Penjualan/index'))
    ->name('penjualan.index');

$penjualanModules = [
    'surat-jalan'    => 'Penjualan/SuratJalan/index',
    'tanda-terima'   => 'Penjualan/TandaTerima/index',
    'invoice'        => 'Penjualan/Invoice/index',
];

foreach ($penjualanModules as $slug => $page) {
    Route::get("/penjualan/{$slug}", fn () => Inertia::render($page))
        ->name('penjualan.'.str_replace('-', '_', $slug));
}
