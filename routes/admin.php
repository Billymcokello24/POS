<?php

// Placeholder admin routes file.
// This file was missing and caused `artisan` to fail during package discovery.
// Add admin-specific routes here if needed.

use Illuminate\Support\Facades\Route;

// Example admin route group (disabled by default):
// Route::middleware(['auth', 'can:access-admin'])->prefix('admin')->group(function () {
//     // Add admin routes here
// });

Route::middleware(['auth', 'super_admin'])->group(function () {
    Route::get('/admin/cms', [\App\Http\Controllers\Admin\CmsController::class, 'index'])->name('admin.cms.index');
    Route::put('/admin/cms', [\App\Http\Controllers\Admin\CmsController::class, 'update'])->name('admin.cms.update');
});
