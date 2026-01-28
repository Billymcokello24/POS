<?php
// Run a subscription STK initiation as the seeded admin and print results
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

try {
    $user = \App\Models\User::where('email', 'admin@pos.com')->first();
    if (! $user) {
        echo json_encode(['ok' => false, 'message' => 'admin user not found']);
        exit(0);
    }

    auth()->loginUsingId($user->id);

    // Create an API-like request (Accept: application/json, X-Requested-With: XMLHttpRequest)
    $server = [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    ];

    $req = Request::create('/subscription', 'POST', [
        'plan_id' => 1,
        'billing_cycle' => 'monthly',
        'payment_method' => 'stk',
        'phone_number' => '0712345678',
    ], [], [], $server);

    // Call the controller
    $ctrl = app(\App\Http\Controllers\Business\SubscriptionController::class);
    $res = $ctrl->initiatePayment($req);

    // Normalize response
    $out = ['ok' => true];
    if (method_exists($res, 'getData')) {
        $out['response'] = $res->getData(true);
    } elseif (method_exists($res, 'getContent')) {
        $parsed = json_decode($res->getContent(), true);
        $out['response'] = $parsed ?: $res->getContent();
    } else {
        $out['response'] = (string) $res;
    }

    // Show recent SubscriptionPayment and MpesaPayment for the business
    $businessId = $user->current_business_id;
    $out['recent_subscription_payments'] = \App\Models\SubscriptionPayment::where('business_id', $businessId)->orderBy('id', 'desc')->take(5)->get()->toArray();
    $out['recent_mpesa_payments'] = \App\Models\MpesaPayment::where('business_id', $businessId)->orderBy('id', 'desc')->take(5)->get()->toArray();

    // Tail last 200 lines for M-Pesa related logs
    $log = [];
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        $lines = array_slice(file($logFile), -400);
        foreach ($lines as $ln) {
            if (stripos($ln, 'M-Pesa') !== false || stripos($ln, 'MPESA') !== false || stripos($ln, 'mpesa_err_') !== false || stripos($ln, 'STK') !== false) {
                $log[] = trim($ln);
            }
        }
    }
    $out['log_snippet'] = $log;

    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
