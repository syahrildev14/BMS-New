<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Pembelian\ListOrderPenawaranController;
use App\Http\Controllers\Pembelian\DaftarPembelanjaanController;
use App\Http\Controllers\Pembelian\DaftarPembelanjaanSupplierController;

Route::prefix('pembelian')->group(function () {

    Route::apiResource(
        'list-order-penawaran',
        ListOrderPenawaranController::class
    );
    Route::apiResource(
        'daftar-pembelanjaan',
        DaftarPembelanjaanController::class
    );
    Route::apiResource(
        'daftar-pembelanjaan-supplier',
        DaftarPembelanjaanSupplierController::class
    )->only([
        'index',
        'store',
        'show',
        'destroy',
    ]);
});
