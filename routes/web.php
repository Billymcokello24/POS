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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

Route::get('/', function (\App\Services\CmsService $cmsService) {
    return Inertia::render('Welcome', [
        'cms' => $cmsService->getContent(),
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('home');

Route::get('/register-business', [\App\Http\Controllers\BusinessAuthController::class, 'create'])->name('business.register');
Route::post('/register-business', [\App\Http\Controllers\BusinessAuthController::class, 'store']);

Route::get('/register', function () {
    return Inertia::render('auth/Register');
})->name('register');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Admin dashboard for super admins (use Admin\DashboardController)
    Route::get('/admin/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])
        ->name('admin.dashboard')
        ->middleware('super_admin');

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

        // Categories (permissioned by feature toggle)
        Route::middleware(['feature:categories'])->group(function () {
            Route::resource('categories', CategoryController::class);
        });
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

    // Ensure POST endpoints for subscription exist (guarded by auth and verified).
    // Some frontend bundles or proxies may still POST to /subscription — register these explicitly.
    Route::post('subscription', [\App\Http\Controllers\Business\SubscriptionController::class, 'initiatePayment'])->name('subscription.store.force');
    Route::post('subscription/pay', [\App\Http\Controllers\Business\SubscriptionController::class, 'initiatePayment'])->name('subscription.pay.force');

    // Fallback route: accept GET and POST and forward POST to the subscription initiatePayment method.
    Route::middleware(['auth', 'verified'])->match(['get', 'post'], 'subscription', function (Request $request) {
        if ($request->isMethod('post')) {
            return app(\App\Http\Controllers\Business\SubscriptionController::class)->initiatePayment($request);
        }

        return app(\App\Http\Controllers\Business\SubscriptionController::class)->index();
    })->name('subscription.fallback');

    // Payment API Routes (internal, require auth)
    Route::prefix('api/payments')->group(function () {
        Route::post('/mpesa/stk-push', [\App\Http\Controllers\Api\PaymentController::class, 'initiateMpesaPayment']);
        Route::post('/mpesa/check-status', [\App\Http\Controllers\Api\PaymentController::class, 'checkMpesaStatus']);
        Route::post('/mpesa/till-payment', [\App\Http\Controllers\Api\PaymentController::class, 'recordTillPayment']);
        Route::post('/card', [\App\Http\Controllers\Api\PaymentController::class, 'processCardPayment']);
        Route::post('/bank-transfer', [\App\Http\Controllers\Api\PaymentController::class, 'recordBankTransfer']);
        Route::post('/cash', [\App\Http\Controllers\Api\PaymentController::class, 'recordCashPayment']);
    });

    // Quick sale endpoint: creates the sale and returns a base64 PDF for immediate printing in the frontend.
    Route::post('/api/sales/quick', [\App\Http\Controllers\SalesController::class, 'quickStore'])
        ->name('api.sales.quick')
        ->middleware(['auth', 'verified', 'feature:pos']);

    // AI Agent internal API (requires auth)
    Route::prefix('api/ai')->group(function () {
        Route::post('/search', [\App\Http\Controllers\Api\AIAgentController::class, 'searchInventory']);
        Route::post('/report', [\App\Http\Controllers\Api\AIAgentController::class, 'generateReport']);
        Route::get('/slow-moving', [\App\Http\Controllers\Api\AIAgentController::class, 'slowMovingProducts']);
        Route::get('/availability', [\App\Http\Controllers\Api\AIAgentController::class, 'productAvailability']);
        Route::post('/chat', [\App\Http\Controllers\Api\AIAgentController::class, 'chat']);
    });

    // AI UI page
    Route::get('/ai', function () {
        return Inertia::render('AI/Index');
    })->name('ai.index');

    // AI Chat page
    Route::get('/ai/chat', function () {
        return Inertia::render('AI/Chat');
    })->name('ai.chat');

});

require __DIR__.'/settings.php';

Route::middleware(['auth', 'super_admin'])->prefix('admin')->group(function () {
    Route::get('/cms', [\App\Http\Controllers\Admin\CmsController::class, 'index'])->name('admin.cms.index');
    Route::put('/cms', [\App\Http\Controllers\Admin\CmsController::class, 'update'])->name('admin.cms.update');

    // Admin subscriptions routes
    Route::get('/subscriptions', [\App\Http\Controllers\Admin\SubscriptionController::class, 'index'])->name('admin.subscriptions.index');
    Route::post('/subscriptions', [\App\Http\Controllers\Admin\SubscriptionController::class, 'store'])->name('admin.subscriptions.store');
    Route::post('/subscriptions/{subscription}/approve', [\App\Http\Controllers\Admin\SubscriptionController::class, 'approve'])->name('admin.subscriptions.approve');
    Route::post('/subscriptions/{subscription}/cancel', [\App\Http\Controllers\Admin\SubscriptionController::class, 'cancel'])->name('admin.subscriptions.cancel');

    // Admin features routes
    // Features index (render UI)
    Route::get('/features', [\App\Http\Controllers\Admin\FeatureController::class, 'index'])->name('admin.features.index');

    // Provide a safe GET redirect so visiting /admin/features/toggle in a browser doesn't raise MethodNotAllowed
    Route::get('/features/toggle', function () {
        return redirect()->route('admin.features.index');
    })->name('admin.features.toggle.redirect');

    Route::post('/features/toggle', [\App\Http\Controllers\Admin\FeatureController::class, 'toggle'])->name('admin.features.toggle');

    // Admin businesses routes
    Route::get('/businesses', [\App\Http\Controllers\Admin\BusinessController::class, 'index'])->name('admin.businesses.index');
    Route::post('/businesses/{business}/toggle-status', [\App\Http\Controllers\Admin\BusinessController::class, 'toggleStatus'])->name('admin.businesses.toggle-status');
    Route::post('/businesses/{business}/reset-admin-password', [\App\Http\Controllers\Admin\BusinessController::class, 'resetAdminPassword'])->name('admin.businesses.reset-password');
    Route::post('/businesses/{business}/impersonate', [\App\Http\Controllers\Admin\BusinessController::class, 'impersonate'])->name('admin.businesses.impersonate');
    Route::post('/businesses/stop-impersonating', [\App\Http\Controllers\Admin\BusinessController::class, 'stopImpersonating'])->name('admin.businesses.stop-impersonating');

    // Admin plans routes
    Route::get('/plans', [\App\Http\Controllers\Admin\PlanController::class, 'index'])->name('admin.plans.index');
    Route::post('/plans', [\App\Http\Controllers\Admin\PlanController::class, 'store'])->name('admin.plans.store');
    Route::put('/plans/{plan}', [\App\Http\Controllers\Admin\PlanController::class, 'update'])->name('admin.plans.update');
    Route::delete('/plans/{plan}', [\App\Http\Controllers\Admin\PlanController::class, 'destroy'])->name('admin.plans.destroy');

    // Admin roles routes
    Route::get('/roles', [\App\Http\Controllers\Admin\RoleController::class, 'index'])->name('admin.roles.index');
    Route::post('/roles', [\App\Http\Controllers\Admin\RoleController::class, 'store'])->name('admin.roles.store');
    Route::put('/roles/{role}', [\App\Http\Controllers\Admin\RoleController::class, 'update'])->name('admin.roles.update');
    Route::delete('/roles/{role}', [\App\Http\Controllers\Admin\RoleController::class, 'destroy'])->name('admin.roles.destroy');

    // Admin audit logs
    Route::get('/audit-logs', [\App\Http\Controllers\Admin\AuditController::class, 'index'])->name('admin.audit.index');
});

// Lightweight SSE endpoint for MPESA checkout streaming. Uses a short-lived token generated by the server
// to avoid exposing session auth to EventSource (which does not send cookies or custom headers).
Route::get('/api/payments/mpesa/stream', function (\Illuminate\Http\Request $request) {
    $checkout = $request->query('checkoutRequestID');
    $token = $request->query('token');

    if (! $checkout || ! $token) {
        return response('Missing parameters', 400);
    }

    $cacheKey = "mpesa_sse_" . $checkout;
    $expected = Cache::get($cacheKey);
    if (! $expected || ! hash_equals((string) $expected, (string) $token)) {
        return response('Unauthorized', 401);
    }

    // StreamedResponse that polls the MpesaPayment record every 2 seconds for updates.
    $response = new StreamedResponse(function () use ($checkout) {
        // Set SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');

        $lastStatus = null;
        $start = time();
        while (true) {
            // Safety: stop after 5 minutes
            if (time() - $start > 300) {
                echo "event: timeout\n";
                echo "data: {\"status\":\"timeout\"}\n\n";
                flush();
                break;
            }

            try {
                $mp = \App\Models\MpesaPayment::where('checkout_request_id', $checkout)->latest()->first();
                if ($mp) {
                    $status = $mp->status ?? 'pending';
                    if ($status !== $lastStatus) {
                        $payload = ['status' => $status, 'data' => $mp->toArray()];
                        echo "data: " . json_encode($payload) . "\n\n";
                        flush();
                        $lastStatus = $status;

                        // If final state, exit
                        if (in_array($status, ['completed','failed','cancelled','failed'])) break;
                    }
                }
            } catch (\Throwable $e) {
                echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
                flush();
            }

            // sleep 2 seconds
            sleep(2);
        }
    });

    return $response;
});

// Token-backed check-status endpoint for polling without session (used when frontend has sse token)
Route::post('/api/payments/mpesa/check-status-token', function (\Illuminate\Http\Request $request) {
    $checkout = $request->input('checkout_request_id') ?? $request->input('checkoutRequestID') ?? null;
    $token = $request->header('X-SSE-TOKEN') ?? $request->input('token');

    if (! $checkout || ! $token) {
        return response()->json(['success' => false, 'message' => 'Missing parameters'], 400);
    }

    $cacheKey = 'mpesa_sse_' . $checkout;
    $expected = Cache::get($cacheKey);
    if (! $expected || ! hash_equals((string) $expected, (string) $token)) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    try {
        $mp = \App\Models\MpesaPayment::where('checkout_request_id', $checkout)->latest()->first();
        if (! $mp) {
            return response()->json(['success' => true, 'status' => 'pending', 'data' => null]);
        }

        return response()->json(['success' => true, 'status' => $mp->status ?? 'pending', 'data' => $mp->toArray()]);
    } catch (\Throwable $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
});
