<?php

use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

// M-Pesa Callback (no auth required - public endpoint for Safaricom)
Route::post('/payments/mpesa/callback', [\App\Http\Controllers\Api\MpesaController::class, 'callback']);

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
