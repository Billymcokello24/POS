<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function (\App\Services\CmsService $cmsService) {
    return Inertia::render('Welcome', [
        'cms' => $cmsService->getContent(),
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('home');

Route::get('/register-business', [\App\Http\Controllers\BusinessAuthController::class, 'create'])->name('business.register');
Route::post('/register-business', [\App\Http\Controllers\BusinessAuthController::class, 'store']);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Core Business Features
    Route::middleware(['feature:pos'])->group(function () {
        // Products
        Route::post('products/import', [\App\Http\Controllers\ProductController::class, 'import'])->name('products.import');
        Route::get('products/import/template', [\App\Http\Controllers\ProductController::class, 'downloadTemplate'])->name('products.import.template');
        Route::resource('products', ProductController::class);
        Route::get('api/products/search', [\App\Http\Controllers\ProductController::class, 'search']);
        Route::get('api/products/scan', [\App\Http\Controllers\ProductController::class, 'scanBarcode']);

        // Sales
        Route::get('sales/export', [SalesController::class, 'export'])->name('sales.export');
        Route::resource('sales', SalesController::class);
        Route::get('sales/{sale}/receipt', [SalesController::class, 'receipt'])->name('sales.receipt');
        Route::post('sales/{sale}/refund', [SalesController::class, 'refund'])->name('sales.refund');

        // Categories
        Route::resource('categories', CategoryController::class);
    });

    // Inventory
    Route::middleware(['feature:inventory'])->group(function () {
        Route::get('inventory/export', [InventoryController::class, 'export'])->name('inventory.export');
        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
        Route::get('inventory/transactions', [InventoryController::class, 'transactions'])->name('inventory.transactions');
    });

    // Reports
    Route::middleware(['feature:reports'])->group(function () {
        Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::get('reports/sales/export', [ReportsController::class, 'export'])->name('reports.sales.export');
        Route::get('reports/sales', [ReportsController::class, 'sales'])->name('reports.sales');
        Route::get('reports/inventory/export', [ReportsController::class, 'exportInventory'])->name('reports.inventory.export');
        Route::get('reports/inventory', [ReportsController::class, 'inventory'])->name('reports.inventory');
        Route::get('reports/export', [ReportsController::class, 'export'])->name('reports.export');
    });

    // Business
    Route::get('business/settings', [BusinessController::class, 'settings'])->name('business.settings');
    Route::put('business/settings', [BusinessController::class, 'updateSettings'])->name('business.update-settings');
    // Test MPESA credentials for current business (AJAX)
    Route::post('business/mpesa/test', [BusinessController::class, 'testMpesa'])->name('business.test-mpesa');

    // Users (RBAC)
    Route::resource('users', UsersController::class);
    Route::post('users/{user}/toggle-status', [UsersController::class, 'toggleStatus'])->name('users.toggle-status');

    // Subscription
    Route::get('subscription', [\App\Http\Controllers\Business\SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('subscription/pay', [\App\Http\Controllers\Business\SubscriptionController::class, 'initiatePayment'])->name('subscription.pay');

    // Payment API Routes (internal, require auth)
    Route::prefix('api/payments')->group(function () {
        Route::post('/mpesa/stk-push', [\App\Http\Controllers\Api\PaymentController::class, 'initiateMpesaPayment']);
        Route::post('/mpesa/check-status', [\App\Http\Controllers\Api\PaymentController::class, 'checkMpesaStatus']);
        Route::post('/mpesa/till-payment', [\App\Http\Controllers\Api\PaymentController::class, 'recordTillPayment']);
        Route::post('/card', [\App\Http\Controllers\Api\PaymentController::class, 'processCardPayment']);
        Route::post('/bank-transfer', [\App\Http\Controllers\Api\PaymentController::class, 'recordBankTransfer']);
        Route::post('/cash', [\App\Http\Controllers\Api\PaymentController::class, 'recordCashPayment']);
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
