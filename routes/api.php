<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PublicSubscriptionController;
use App\Http\Controllers\Api\ContactController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\MpesaController;
use Illuminate\Support\Facades\Broadcast;

// Broadcasting auth is configured in bootstrap/app.php with withBroadcasting()
// No need to register it here again

// Public Subscription API (no auth required for welcome page)
Route::prefix('public')->group(function () {
    Route::get('/plans', [PublicSubscriptionController::class, 'getPlans']);
    Route::post('/subscriptions/create', [PublicSubscriptionController::class, 'createPendingSubscription']);
    Route::post('/subscriptions/payment/initiate', [PublicSubscriptionController::class, 'initiateMpesaPayment']);
    Route::post('/subscriptions/payment/status', [PublicSubscriptionController::class, 'checkPaymentStatus']);
});

// Contact Form API (no auth required)
Route::post('/contact', [ContactController::class, 'send']);

// Download Export Files (auth required)
Route::get('/download/export', function (Request $request) {
    if (!auth()->check()) {
        abort(403, 'Unauthorized');
    }

    $path = base64_decode($request->query('path'));

    // Security: ensure path is within exports directory
    if (!str_starts_with($path, 'exports/')) {
        abort(403, 'Invalid path');
    }

    if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
        abort(404, 'File not found');
    }

    return \Illuminate\Support\Facades\Storage::disk('local')->download($path);
})->name('api.download.export');

// M-Pesa Callback (no auth required - public endpoint for Safaricom)
Route::post('/payments/mpesa/callback', [MpesaController::class, 'stkCallback']);

// M-Pesa C2B validation and confirmation endpoints
Route::post('/payments/mpesa/validation', [MpesaController::class, 'validation']);
Route::post('/payments/mpesa/confirmation', [MpesaController::class, 'confirmation']);

// Friendly message when someone visits callback URL in browser
Route::get('/payments/mpesa/callback', function () {
    return response()->json([
        'status' => 'active',
        'message' => 'M-Pesa Callback Endpoint',
        'info' => 'This endpoint receives POST requests from Safaricom M-Pesa API',
        'note' => 'If you are seeing this, the callback URL is correctly configured and accessible',
        'callback_url' => config('mpesa.callback_url'),
        'environment' => config('mpesa.environment'),
        'timestamp' => now()->toIso8601String()
    ]);
});

// Note: Protected payment routes are now in routes/web.php for proper CSRF handling

// Stateless AI API (dev use) - requires AI_API_TOKEN set in env and X-AI-API-KEY header
// Moved to /api/ai-dev/{action} to avoid colliding with session-protected /api/ai/* web routes
Route::middleware([])->group(function () {
    Route::any('/ai-dev/{action}', function (Request $request, $action) {
        $token = $request->header('X-AI-API-KEY') ?? $request->query('token');
        $expected = config('ai.api_token');
        if (!$expected || !$token || !hash_equals($expected, $token)) {
            return response()->json(['success' => false, 'message' => 'Invalid API token'], 401);
        }

        $controller = app(\App\Http\Controllers\Api\AIAgentController::class);

        switch ($action) {
            case 'search':
                return $controller->searchInventory($request);
            case 'report':
                return $controller->generateReport($request);
            case 'slow-moving':
                return $controller->slowMovingProducts($request);
            case 'availability':
                return $controller->productAvailability($request);
            case 'chat':
                return $controller->chat($request);
            default:
                return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
    });
});

// Protected route for M-Pesa STK push (requires authentication)
Route::middleware('auth:sanctum')->post('/payments/mpesa/stk-push', [MpesaController::class, 'initiateMpesaPayment']);
Route::post('/payments/mpesa/check-status', [MpesaController::class, 'checkMpesaStatus']);
Route::post('/subscription/payment/status', [MpesaController::class, 'checkMpesaStatus']);
Route::post('/payments/mpesa/till-payment', [MpesaController::class, 'recordTillPayment']);
Route::post('/card', [PaymentController::class, 'processCardPayment']);

// Subscription payment API (STK push)
Route::post('/subscription/pay', [\App\Http\Controllers\Api\SubscriptionPaymentController::class, 'initiate']);
