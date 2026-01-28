<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$user = App\Models\User::whereNotNull('current_business_id')->first();
if (! $user) {
    echo "NO_USER\n";
    exit(1);
}

// Log in as this user
Auth::loginUsingId($user->id);

$plan = App\Models\Plan::first();
if (! $plan) {
    echo "NO_PLAN\n";
    exit(1);
}

$payload = [
    'plan_id' => $plan->id,
    'billing_cycle' => 'monthly',
    'phone_number' => '254708374149',
    'amount' => $plan->price_monthly ?: 1,
];

$req = Request::create('/subscription/api/pay', 'POST', [], [], [], [], json_encode($payload));
$req->headers->set('Content-Type', 'application/json');
$req->headers->set('Accept', 'application/json');

try {
    $controller = app(\App\Http\Controllers\Api\SubscriptionPaymentController::class);
    $resp = $controller->initiate($req);
    if (method_exists($resp, 'getContent')) {
        echo $resp->getContent() . "\n";
    } else {
        var_export($resp);
    }
} catch (\Throwable $e) {
    echo "EXCEPTION:\n" . $e->getMessage() . "\n" . $e->getTraceAsString();
}
