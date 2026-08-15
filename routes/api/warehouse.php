<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Warehouse\InboundController;
use App\Http\Controllers\Warehouse\ReturRusakController;

Route::prefix('warehouse')->group(function () {

    Route::apiResource('inbound', InboundController::class);
    Route::apiResource('retur-rusak', ReturRusakController::class);
    
});
