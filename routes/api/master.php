<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Master\SatuanController;
use App\Http\Controllers\Master\BarangController;
use App\Http\Controllers\Master\SupplierController;
use App\Http\Controllers\Master\MitraController;
use App\Http\Controllers\Master\SppgController;
use App\Http\Controllers\Master\GudangController;
use App\Http\Controllers\Master\ArmadaController;
use App\Http\Controllers\Master\KaryawanController;
use App\Http\Controllers\Master\BankRekeningController;
use App\Http\Controllers\Master\TemplateSuratController;


Route::prefix('master')->group(function () {

    Route::apiResource('satuan', SatuanController::class);
    Route::apiResource('barang', BarangController::class);
    Route::apiResource('supplier', SupplierController::class);
    Route::apiResource('mitra', MitraController::class);
    Route::apiResource('sppg', SppgController::class);
    Route::apiResource('gudang', GudangController::class);
    Route::apiResource('armada', ArmadaController::class);
    Route::apiResource('karyawan', KaryawanController::class);
    Route::apiResource('bank-rekening', BankRekeningController::class);
    Route::apiResource('template-surat', TemplateSuratController::class);

});