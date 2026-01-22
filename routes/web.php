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

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Products
    Route::resource('products', ProductController::class);
    Route::get('api/products/search', [ProductController::class, 'search']);
    Route::get('api/products/scan', [ProductController::class, 'scanBarcode']);

    // Sales
    Route::get('sales/export', [SalesController::class, 'export'])->name('sales.export');
    Route::resource('sales', SalesController::class);
    Route::get('sales/{sale}/receipt', [SalesController::class, 'receipt'])->name('sales.receipt');
    Route::post('sales/{sale}/refund', [SalesController::class, 'refund'])->name('sales.refund');

    // Categories
    Route::resource('categories', CategoryController::class);

    // Inventory
    Route::get('inventory/export', [InventoryController::class, 'export'])->name('inventory.export');
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
    Route::get('inventory/transactions', [InventoryController::class, 'transactions'])->name('inventory.transactions');

    // Reports
    Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('reports/sales/export', [ReportsController::class, 'export'])->name('reports.sales.export');
    Route::get('reports/sales', [ReportsController::class, 'sales'])->name('reports.sales');
    Route::get('reports/inventory/export', [ReportsController::class, 'exportInventory'])->name('reports.inventory.export');
    Route::get('reports/inventory', [ReportsController::class, 'inventory'])->name('reports.inventory');
    Route::get('reports/export', [ReportsController::class, 'export'])->name('reports.export');

    // Business
    Route::get('business/settings', [BusinessController::class, 'settings'])->name('business.settings');
    Route::put('business/settings', [BusinessController::class, 'updateSettings'])->name('business.update-settings');

    // Users (RBAC)
    Route::resource('users', UsersController::class);
    Route::post('users/{user}/toggle-status', [UsersController::class, 'toggleStatus'])->name('users.toggle-status');
});

require __DIR__.'/settings.php';
