<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Penjualan\PenjualanController;
use App\Http\Controllers\Penjualan\SuratJalanController;
use App\Http\Controllers\Penjualan\TandaTerimaController;
use App\Http\Controllers\Penjualan\InvoiceController;

Route::prefix('penjualan')->group(function () {

    Route::apiResource(
        'penjualan',
        PenjualanController::class
    );
});

Route::prefix('surat-jalan')->group(function () {

    Route::get(
        '/{penjualan}',
        [SuratJalanController::class, 'show']
    );

    Route::get(
        '/{penjualan}/pdf',
        [SuratJalanController::class, 'downloadPdf']
    );
});

Route::apiResource(
    'tanda-terima',
    TandaTerimaController::class
);

Route::prefix('invoice')->group(function () {

    Route::get(
        '/{penjualan}',
        [InvoiceController::class, 'show']
    );

    Route::get(
        '/{penjualan}/pdf',
        [InvoiceController::class, 'downloadPdf']
    );

});