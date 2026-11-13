<?php

use App\Http\Controllers\Admin\AppearanceController;
use App\Http\Controllers\Admin\ArmadaController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\BankAccountController;
use App\Http\Controllers\Admin\DapurController;
use App\Http\Controllers\Admin\DocumentTemplateController;
use App\Http\Controllers\Admin\DriverController;
use App\Http\Controllers\Admin\ItemController;
use App\Http\Controllers\Admin\PricingController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SatuanController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Dapur\OrderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\SupplierBillController;
use App\Http\Controllers\Gudang\PurchaseOrderController as GudangPurchaseOrderController;
use App\Http\Controllers\Gudang\StockController;
use App\Http\Controllers\Ops\DeliveryNoteController;
use App\Http\Controllers\Ops\DeliveryVerificationController;
use App\Http\Controllers\Ops\RevisionController;
use App\Http\Controllers\Purchasing\PurchaseNoteController;
use App\Http\Controllers\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Purchasing\ShoppingListController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('dapur')->name('dapur.')->middleware('permission:orders.manage')->group(function () {
        Route::resource('orders', OrderController::class)
            ->only(['index', 'create', 'store', 'show']);
        Route::post('orders/{order}/stt', [OrderController::class, 'submitStt'])->name('orders.stt');
        Route::post('orders/{order}/items', [OrderController::class, 'addItems'])->name('orders.items.store');
        Route::patch('orders/{order}/items/{orderItem}', [OrderController::class, 'updateItem'])->name('orders.items.update');
        Route::delete('orders/{order}/items/{orderItem}', [OrderController::class, 'destroyItem'])->name('orders.items.destroy');
        Route::get('documents/{suratJalan}/download', [OrderController::class, 'downloadDocument'])
            ->name('documents.download');
        Route::get('documents/{suratJalan}/tanda-terima', [OrderController::class, 'downloadDocumentTandaTerima'])
            ->name('documents.tanda-terima.download');
        Route::post('documents/{suratJalan}/photos', [OrderController::class, 'storeDocumentPhotos'])
            ->name('documents.photos.store');
        Route::get('documents/{suratJalan}/photos/{photo}', [OrderController::class, 'showDocumentPhoto'])
            ->name('documents.photos.show');
        Route::delete('documents/{suratJalan}/photos/{photo}', [OrderController::class, 'destroyDocumentPhoto'])
            ->name('documents.photos.destroy');
    });

    Route::prefix('ops')->name('ops.')->group(function () {
        Route::resource('delivery-notes', DeliveryNoteController::class)
            ->only(['index', 'store'])
            ->middleware('permission:delivery-notes.manage');
        Route::get('delivery-notes/{suratJalan}/download', [DeliveryNoteController::class, 'download'])
            ->middleware('permission:delivery-notes.manage')
            ->name('delivery-notes.download');
        Route::get('delivery-notes/{suratJalan}/tanda-terima', [DeliveryNoteController::class, 'downloadTandaTerima'])
            ->middleware('permission:delivery-notes.manage')
            ->name('delivery-notes.tanda-terima.download');

        Route::middleware('permission:delivery-verifications.manage')->group(function () {
            Route::get('verifications', [DeliveryVerificationController::class, 'index'])->name('verifications.index');
            Route::get('verifications/{suratJalan}', [DeliveryVerificationController::class, 'show'])->name('verifications.show');
            Route::post('verifications/{suratJalan}/verify', [DeliveryVerificationController::class, 'verify'])->name('verifications.verify');
            Route::post('verifications/{suratJalan}/discrepancy', [DeliveryVerificationController::class, 'discrepancy'])->name('verifications.discrepancy');
            Route::get('verifications/{suratJalan}/photos/{photo}', [DeliveryVerificationController::class, 'photo'])->name('verifications.photos.show');
        });

        Route::middleware('permission:revisions.approve')->group(function () {
            Route::get('revisions', [RevisionController::class, 'index'])->name('revisions.index');
            Route::post('revisions/{revision}/approve', [RevisionController::class, 'approve'])->name('revisions.approve');
            Route::post('revisions/{revision}/reject', [RevisionController::class, 'reject'])->name('revisions.reject');
        });
    });

    Route::prefix('purchasing')->name('purchasing.')->middleware('permission:purchase-orders.manage')->group(function () {
        Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('purchase-orders/tanggal/{date}', [PurchaseOrderController::class, 'byDate'])
            ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
            ->name('purchase-orders.by-date');
        Route::patch('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
        Route::post('purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])->name('purchase-orders.send');
    });

    Route::prefix('purchasing')->name('purchasing.')->middleware('permission:purchase-notes.manage')->group(function () {
        Route::get('shopping-list', [ShoppingListController::class, 'index'])->name('shopping-list.index');
        Route::get('purchase-notes', [PurchaseNoteController::class, 'index'])->name('purchase-notes.index');
        Route::get('purchase-notes/create', [PurchaseNoteController::class, 'create'])->name('purchase-notes.create');
        Route::post('purchase-notes', [PurchaseNoteController::class, 'store'])->name('purchase-notes.store');
        Route::get('purchase-notes/tanda-terima/{suratJalan}/supplier/{supplier}', [PurchaseNoteController::class, 'createWet'])->name('purchase-notes.create-wet');
        Route::post('purchase-notes/tanda-terima/{suratJalan}/supplier/{supplier}', [PurchaseNoteController::class, 'storeWet'])->name('purchase-notes.store-wet');
        Route::get('purchase-notes/{purchaseNote}/edit', [PurchaseNoteController::class, 'edit'])->name('purchase-notes.edit');
        Route::put('purchase-notes/{purchaseNote}', [PurchaseNoteController::class, 'update'])->name('purchase-notes.update');
    });

    Route::prefix('gudang')->name('gudang.')->middleware('permission:stock.manage')->group(function () {
        Route::get('purchase-orders', [GudangPurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('purchase-orders/tanggal/{date}', [GudangPurchaseOrderController::class, 'byDate'])
            ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
            ->name('purchase-orders.by-date');
        Route::post('purchase-orders/{purchaseOrder}/receive', [GudangPurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
        Route::get('stock', [StockController::class, 'index'])->name('stock.index');
        Route::get('stock/{item}/history', [StockController::class, 'history'])->name('stock.history');
        Route::post('stock/{item}/opname', [StockController::class, 'opname'])->name('stock.opname');
    });

    Route::prefix('finance')->name('finance.')->group(function () {
        Route::middleware('permission:invoices.manage')->group(function () {
            Route::resource('invoices', InvoiceController::class)
                ->only(['index', 'store']);
            Route::get('invoices/tanggal/{date}', [InvoiceController::class, 'byDate'])
                ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
                ->name('invoices.by-date');
            Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
            Route::post('invoices/{invoice}/regenerate-pdf', [InvoiceController::class, 'regeneratePdf'])->name('invoices.regenerate-pdf');
        });

        // Checker: a separate permission from the Maker's invoices.manage, so
        // approval AND marking as paid can be restricted to a different
        // user/role (maker-checker) — "money has actually moved" is a
        // checker-level action, not something the Maker should self-certify.
        Route::middleware('permission:invoices.approve')->group(function () {
            Route::post('invoices/{invoice}/approve', [InvoiceController::class, 'approve'])->name('invoices.approve');
            Route::post('invoices/{invoice}/reject', [InvoiceController::class, 'reject'])->name('invoices.reject');
            Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
        });

        // Accounts Payable — same maker-checker shape as invoices, billing the
        // Supplier instead of the Dapur.
        Route::middleware('permission:supplier-bills.manage')->group(function () {
            Route::resource('supplier-bills', SupplierBillController::class)
                ->only(['index', 'store']);
            Route::get('supplier-bills/tanggal/{date}', [SupplierBillController::class, 'byDate'])
                ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
                ->name('supplier-bills.by-date');
        });

        Route::middleware('permission:supplier-bills.approve')->group(function () {
            Route::post('supplier-bills/{supplierBill}/approve', [SupplierBillController::class, 'approve'])->name('supplier-bills.approve');
            Route::post('supplier-bills/{supplierBill}/reject', [SupplierBillController::class, 'reject'])->name('supplier-bills.reject');
            Route::post('supplier-bills/{supplierBill}/pay', [SupplierBillController::class, 'pay'])->name('supplier-bills.pay');
        });
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
        Route::resource('document-templates', DocumentTemplateController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middleware('permission:document-templates.manage');
        Route::resource('armadas', ArmadaController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middleware('permission:fleet.manage');
        Route::resource('drivers', DriverController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middleware('permission:fleet.manage');
        Route::resource('bank-accounts', BankAccountController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middleware('permission:bank-accounts.manage');

        Route::middleware('permission:appearance.manage')->group(function () {
            Route::get('appearance', [AppearanceController::class, 'index'])->name('appearance.index');
            Route::put('appearance', [AppearanceController::class, 'update'])->name('appearance.update');
            Route::delete('appearance', [AppearanceController::class, 'destroy'])->name('appearance.destroy');
        });

        Route::post('backups/export', [BackupController::class, 'export'])
            ->middleware('permission:system.backup.manage')
            ->name('backups.export');

        Route::get('audit-logs', [AuditLogController::class, 'index'])
            ->middleware('permission:system.audit-log.view')
            ->name('audit-logs.index');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
