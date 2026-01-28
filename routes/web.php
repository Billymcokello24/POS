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
use App\Http\Controllers\Auth\LogoutController;
use Illuminate\Support\Facades\Log;

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

    // API-like endpoint for subscription STK that uses session auth (CSRF protected)
    Route::post('subscription/api/pay', [\App\Http\Controllers\Api\SubscriptionPaymentController::class, 'initiate'])->name('subscription.api.pay');

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

// Public endpoint for M-Pesa STK callback (Safaricom will POST here).
// Intentionally kept outside auth middleware so external callbacks can reach it.
Route::post('/api/payments/mpesa/callback', [\App\Http\Controllers\Api\PaymentController::class, 'mpesaCallback']);

Route::middleware(['auth', 'super_admin'])->prefix('admin')->group(function () {
    Route::get('/cms', [\App\Http\Controllers\Admin\CmsController::class, 'index'])->name('admin.cms.index');
    Route::put('/cms', [\App\Http\Controllers\Admin\CmsController::class, 'update'])->name('admin.cms.update');
    // Test platform MPESA credentials (super-admin only)
    Route::post('/cms/test-mpesa', [\App\Http\Controllers\Admin\CmsController::class, 'testPlatformMpesa'])->name('admin.cms.test_mpesa');

    // Admin subscriptions routes
    Route::get('/subscriptions', [\App\Http\Controllers\Admin\SubscriptionController::class, 'index'])->name('admin.subscriptions.index');
    Route::post('/subscriptions', [\App\Http\Controllers\Admin\SubscriptionController::class, 'store'])->name('admin.subscriptions.store');
    Route::post('/subscriptions/{subscription}/approve', [\App\Http\Controllers\Admin\SubscriptionController::class, 'approve'])->name('admin.subscriptions.approve');
    Route::post('/subscriptions/{subscription}/cancel', [\App\Http\Controllers\Admin\SubscriptionController::class, 'cancel'])->name('admin.subscriptions.cancel');
    // Manual reconcile endpoint for superadmins to trigger auto-activation check
    Route::post('/subscriptions/reconcile', [\App\Http\Controllers\Admin\SubscriptionController::class, 'reconcile'])->name('admin.subscriptions.reconcile');

    // Admin features routes
    // Features index (render UI)
    Route::get('/features', [\App\Http\Controllers\Admin\FeatureController::class, 'index'])->name('admin.features.index');

    // Provide a safe GET redirect so visiting /admin/features/toggle in a browser doesn't raise MethodNotAllowed
    Route::get('/features/toggle', function () {
        return redirect()->route('admin.features.index');
    })->name('admin.features.toggle.redirect');

    Route::post('/features/toggle', [\App\Http\Controllers\Admin\FeatureController::class, 'toggle'])->name('admin.features.toggle');

    // Quick admin JSON view for subscription payments (last 50 records). Protected by admin group middleware.
    Route::get('/subscription-payments', function () {
        return response()->json(\App\Models\SubscriptionPayment::orderBy('id', 'desc')->take(50)->get());
    })->name('admin.subscription-payments');

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
    Route::delete('/roles/{role}', [\AppHttp\Controllers\Admin\RoleController::class, 'destroy'])->name('admin.roles.destroy');

    // Admin audit logs
    Route::get('/audit-logs', [\App\Http\Controllers\Admin\AuditController::class, 'index'])->name('admin.audit.index');
});

// Lightweight SSE endpoint for MPESA checkout streaming. Uses a short-lived token generated by the server
// to avoid exposing session auth to EventSource (which does not send cookies or custom headers).
Route::get('/api/payments/mpesa/stream', function (\Illuminate\Http\Request $request) {
    $checkout = $request->query('checkoutRequestID');
    $token = $request->query('token');

    if (! $checkout) {
        return response('Missing parameters', 400);
    }

    // If a token is provided, validate it against cache (token-backed clients)
    if ($token) {
        $cacheKey = "mpesa_sse_" . $checkout;
        $expected = Cache::get($cacheKey);
        if (! $expected || ! hash_equals((string) $expected, (string) $token)) {
            return response('Unauthorized', 401);
        }
    } else {
        // No token: allow same-origin/session-authenticated clients to connect.
        // Ensure user is authenticated and the checkout belongs to their current business.
        $user = $request->user();
        if (! $user) {
            return response('Unauthorized', 401);
        }

        try {
            $businessId = $user->current_business_id ?? null;
            if (! $businessId) return response('Unauthorized', 401);

            $mp = \App\Models\MpesaPayment::where('checkout_request_id', $checkout)
                ->where('business_id', $businessId)
                ->latest()
                ->first();

            if (! $mp) {
                // No matching MpesaPayment for this user/business -> reject
                return response('Unauthorized', 401);
            }
        } catch (\Throwable $e) {
            return response('Unauthorized', 401);
        }
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

// Short-lived token endpoint for SSE (frontend requests token, then opens EventSource with token)
Route::post('/api/payments/mpesa/sse-token', function (\Illuminate\Http\Request $request) {
    $checkout = $request->input('checkout_request_id') ?? $request->input('checkoutRequestID') ?? null;
    if (! $checkout) {
        return response()->json(['success' => false, 'message' => 'Missing checkout_request_id'], 400);
    }

    $user = $request->user();
    if (! $user) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $businessId = $user->current_business_id ?? null;
    if (! $businessId) {
        return response()->json(['success' => false, 'message' => 'No business context'], 400);
    }

    // Ensure the checkout belongs to this business
    $mp = \App\Models\MpesaPayment::where('checkout_request_id', $checkout)
        ->where('business_id', $businessId)
        ->latest()
        ->first();

    if (! $mp) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    // Generate short-lived token and store in cache
    $token = \Illuminate\Support\Str::random(48);
    $cacheKey = 'mpesa_sse_' . $checkout;
    \Illuminate\Support\Facades\Cache::put($cacheKey, $token, now()->addMinutes(5));

    return response()->json(['success' => true, 'token' => $token]);
})->middleware(['auth']);

// Persist SubscriptionPayment
Route::post('/api/subscription/finalize', [\App\Http\Controllers\Api\SubscriptionPaymentController::class, 'finalize'])->middleware(['auth']);

// Ensure our debug-logging logout route is available (will be used for troubleshooting 419)
Route::post('/logout', [LogoutController::class, 'destroy'])->name('logout.debug');

// Debug route: accept POST and bypass CSRF to inspect cookies/session for troubleshooting logout 419
Route::post('/debug/logout-no-csrf', function (Request $request) {
    $payload = [
        'session_id' => $request->session()->getId(),
        'session_token' => $request->session()->token(),
        'csrf_meta' => csrf_token(),
        'request_input_token' => $request->input('_token'),
        'cookies' => $request->cookies->all(),
        'headers_cookie' => $request->headers->get('cookie'),
        'headers' => $request->headers->all(),
        'url' => $request->fullUrl(),
    ];

    Log::info('Debug logout-no-csrf called', $payload);

    return response()->json($payload);
})->middleware('auth')->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)->name('debug.logout_no_csrf');

// Lightweight CSRF cookie endpoint (Sanctum-like) — ensures frontend can fetch XSRF token
Route::get('/sanctum/csrf-cookie', function (Request $request) {
    // Return a 204 and set the XSRF-TOKEN cookie using Laravel's csrf_token()
    $response = response()->noContent();
    // Make cookie accessible to JS (httpOnly = false) so frontend can read it and set the X-XSRF-TOKEN header
    $cookie = cookie('XSRF-TOKEN', urlencode(csrf_token()), 120, '/', null, config('session.secure'), false, false, 'Lax');
    return $response->withCookie($cookie);
});

Route::post('/test-csrf', function (Illuminate\Http\Request $request) {
    return response()->json([
        'csrf_token' => csrf_token(),
        'session_id' => session()->getId(),
    ]);
});

// SSE endpoint for business-specific updates (authenticated)
Route::middleware(['auth'])->get('/sse/business-stream', [\App\Http\Controllers\SseController::class, 'businessStream'])->name('sse.business_stream');

// Development helper: push a test SSE event for current business (local only)
Route::post('/debug/sse/push', function (Request $request) {
    if (config('app.env') !== 'local') {
        return response()->json(['success' => false, 'message' => 'Not available in this environment'], 403);
    }

    $user = $request->user();
    if (! $user) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $businessId = $user->current_business_id;
    if (! $businessId) {
        return response()->json(['success' => false, 'message' => 'No business context'], 400);
    }

    $type = $request->input('type', 'debug.ping');
    $payload = $request->input('payload', []);

    try {
        \App\Services\SseService::pushBusinessEvent($businessId, $type, (array) $payload);
        return response()->json(['success' => true, 'message' => 'Event pushed', 'type' => $type]);
    } catch (\Throwable $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
})->middleware(['auth'])->name('debug.sse.push');

// Lightweight polling endpoint for clients that can't use SSE
Route::get('/realtime/status', [\App\Http\Controllers\RealtimeController::class, 'status'])->middleware('auth')->name('realtime.status');

// Local-only debug: create a product quickly for testing manual creation flow
Route::post('/debug/products/create', function (\Illuminate\Http\Request $request) {
    if (config('app.env') !== 'local') {
        return response()->json(['success' => false, 'message' => 'Not available in this environment'], 403);
    }

    $user = $request->user();
    if (! $user) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);

    $businessId = $user->current_business_id;
    if (! $businessId) return response()->json(['success' => false, 'message' => 'No business context'], 400);

    $payload = $request->only(['name', 'cost_price', 'selling_price', 'quantity']);
    if (empty($payload['name']) || ! isset($payload['selling_price'])) {
        return response()->json(['success' => false, 'message' => 'name and selling_price are required'], 400);
    }

    try {
        $product = \App\Models\Product::create(array_merge([
            'business_id' => $businessId,
            'name' => $payload['name'],
            'sku' => 'DBG-'.strtoupper(\Illuminate\Support\Str::random(6)),
            'barcode' => null,
            'description' => null,
            'category_id' => null,
            'cost_price' => $payload['cost_price'] ?? 0,
            'selling_price' => $payload['selling_price'],
            'quantity' => $payload['quantity'] ?? 0,
            'reorder_level' => 10,
            'unit' => 'pcs',
            'track_inventory' => true,
            'is_active' => true,
        ], []));

        return response()->json(['success' => true, 'product' => $product]);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Debug product create failed: '.$e->getMessage(), ['exception' => (string) $e]);
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
})->middleware('auth')->name('debug.product_create');

// Polling endpoint: return current/pending subscription status for the authenticated business
Route::match(['get','post'], '/api/business/{business}/subscription-status', function (\Illuminate\Http\Request $request, \App\Models\Business $business) {
    $user = $request->user();
    if (! $user) return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);

    // Ensure user belongs to this business (or is super admin)
    if (! $user->is_super_admin && ! $user->businesses()->where('businesses.id', $business->id)->exists()) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $active = $business->activeSubscription()->first();
    $pending = $business->subscriptions()->where('status', 'pending_verification')->latest()->first();

    return response()->json([
        'success' => true,
        'active' => $active ? [
            'id' => $active->id,
            'plan_name' => $active->plan_name,
            'starts_at' => $active->starts_at?->toDateTimeString(),
            'ends_at' => $active->ends_at?->toDateTimeString(),
            'status' => $active->status,
        ] : null,
        'pending' => $pending ? [
            'id' => $pending->id,
            'plan_name' => $pending->plan_name,
            'created_at' => $pending->created_at?->toDateTimeString(),
            'status' => $pending->status,
        ] : null,
    ]);
})->middleware(['auth']);

// Debug helper: force-activate a subscription (owner or super-admin) — safe for local use
Route::post('/api/subscription/{subscription}/force-activate', function (\Illuminate\Http\Request $request, \App\Models\Subscription $subscription) {
    $user = $request->user();
    if (! $user) return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);

    // Only allow if user belongs to the business or is super admin
    if (! $user->is_super_admin && $subscription->business_id !== ($user->current_business_id ?? null)) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    try {
        // ensure plan_name exists
        if (! $subscription->plan_name && $subscription->plan_id) {
            try { $plan = \App\Models\Plan::find($subscription->plan_id); if ($plan) $subscription->update(['plan_name' => $plan->name]); } catch (\Throwable $_) {}
        }

        if (! method_exists($subscription->business, 'activateSubscription')) {
            return response()->json(['success' => false, 'message' => 'Activation helper unavailable'], 500);
        }

        $transactionId = $request->input('transaction_id') ?? $subscription->transaction_id ?? ('FORCE-' . strtoupper(uniqid()));

        $subscription->business->activateSubscription($subscription, $transactionId, ['source' => 'force_activate_endpoint']);

        try { \App\Services\SseService::pushBusinessEvent($subscription->business_id, 'subscription.activated', ['id' => $subscription->id, 'status' => 'active']); } catch (\Throwable $_) {}

        return response()->json(['success' => true, 'message' => 'Subscription activated (forced)', 'subscription' => $subscription->fresh()->toArray()]);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Force activate failed', ['error' => $e->getMessage(), 'subscription_id' => $subscription->id]);
        return response()->json(['success' => false, 'message' => 'Activation failed', 'error' => $e->getMessage()], 500);
    }
})->middleware(['auth']);

// Debug helper: show subscription payments for the current business (auth required)
Route::get('/my/subscription-payments', function (\Illuminate\Http\Request $request) {
    $user = $request->user();
    if (! $user) return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    $businessId = $user->current_business_id ?? null;
    if (! $businessId) return response()->json(['success' => false, 'message' => 'No business selected'], 400);

    $rows = \App\Models\SubscriptionPayment::where('business_id', $businessId)->orderBy('created_at', 'desc')->take(50)->get();
    return response()->json(['success' => true, 'data' => $rows]);
})->name('my.subscription.payments');

