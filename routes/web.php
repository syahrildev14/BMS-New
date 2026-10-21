<?php

use App\Http\Controllers\Admin\DapurController;
use App\Http\Controllers\Admin\ItemController;
use App\Http\Controllers\Admin\PricingController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SatuanController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Dapur\OrderController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Gudang\PurchaseOrderController as GudangPurchaseOrderController;
use App\Http\Controllers\Gudang\StockController;
use App\Http\Controllers\Ops\DeliveryNoteController;
use App\Http\Controllers\Ops\RevisionController;
use App\Http\Controllers\Purchasing\PurchaseOrderController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::prefix('dapur')->name('dapur.')->middleware('permission:orders.manage')->group(function () {
        Route::resource('orders', OrderController::class)
            ->only(['index', 'create', 'store', 'show']);
    });

    Route::prefix('ops')->name('ops.')->group(function () {
        Route::resource('delivery-notes', DeliveryNoteController::class)
            ->only(['index', 'store'])
            ->middleware('permission:delivery-notes.manage');

        Route::middleware('permission:revisions.approve')->group(function () {
            Route::get('revisions', [RevisionController::class, 'index'])->name('revisions.index');
            Route::post('revisions/{revision}/approve', [RevisionController::class, 'approve'])->name('revisions.approve');
            Route::post('revisions/{revision}/reject', [RevisionController::class, 'reject'])->name('revisions.reject');
        });
    });

    Route::prefix('purchasing')->name('purchasing.')->middleware('permission:purchase-orders.manage')->group(function () {
        Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::patch('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
        Route::post('purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])->name('purchase-orders.send');
    });

    Route::prefix('gudang')->name('gudang.')->middleware('permission:stock.manage')->group(function () {
        Route::get('purchase-orders', [GudangPurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::post('purchase-orders/{purchaseOrder}/receive', [GudangPurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
        Route::get('stock', [StockController::class, 'index'])->name('stock.index');
    });

    Route::prefix('finance')->name('finance.')->middleware('permission:invoices.manage')->group(function () {
        Route::resource('invoices', InvoiceController::class)
            ->only(['index', 'store']);
        Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('pricing', PricingController::class)
            ->only(['index', 'store'])
            ->middleware('permission:pricing.manage');
        Route::resource('roles', RoleController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middleware('permission:roles.manage');
        Route::resource('users', UserController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middleware('permission:users.manage');
        Route::resource('items', ItemController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middleware('permission:items.manage');
        Route::resource('satuans', SatuanController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middleware('permission:satuans.manage');
        Route::resource('suppliers', SupplierController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middleware('permission:suppliers.manage');
        Route::resource('dapurs', DapurController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middleware('permission:dapurs.manage');
    });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
