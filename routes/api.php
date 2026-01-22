<?php

use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

// M-Pesa Callback (no auth required - public endpoint for Safaricom)
Route::post('/payments/mpesa/callback', [PaymentController::class, 'mpesaCallback']);

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

// Protected Payment API Routes - require authentication
Route::middleware(['web', 'auth'])->prefix('payments')->group(function () {
    // M-Pesa STK Push
    Route::post('/mpesa/stk-push', [PaymentController::class, 'initiateMpesaPayment']);
    Route::post('/mpesa/check-status', [PaymentController::class, 'checkMpesaStatus']);
    Route::post('/mpesa/till-payment', [PaymentController::class, 'recordTillPayment']);

    // Card Payment
    Route::post('/card', [PaymentController::class, 'processCardPayment']);

    // Bank Transfer
    Route::post('/bank-transfer', [PaymentController::class, 'recordBankTransfer']);

    // Cash Payment
    Route::post('/cash', [PaymentController::class, 'recordCashPayment']);
});



