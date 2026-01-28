<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

try {
    $user = \App\Models\User::first();
    if (! $user) {
        echo "NO_USER\n";
        exit(1);
    }
    auth()->loginUsingId($user->id);

    $business = $user->currentBusiness;
    if (! $business) {
        echo "NO_BUSINESS\n";
        exit(1);
    }

    $plan = \App\Models\Plan::first();
    if (! $plan) {
        echo "NO_PLAN\n";
        exit(1);
    }

    $req = Request::create('/api/subscription/initiate', 'POST', [
        'plan_id' => $plan->id,
        'billing_cycle' => 'monthly',
        'phone_number' => '254700000000',
        'amount' => $plan->price_monthly ?? 100,
    ], [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

    $ctrl = app(\App\Http\Controllers\Api\SubscriptionPaymentController::class);
    $res = $ctrl->initiate($req);

    if (method_exists($res, 'getContent')) {
        echo "CONTROLLER_RESPONSE:\n" . $res->getContent() . "\n";
    } else {
        echo "CONTROLLER_RESPONSE: (no getContent)\n";
        var_export($res);
    }

    echo "\nRECENT subscription payments (model query):\n";
    $payments = \App\Models\SubscriptionPayment::orderBy('id','desc')->take(10)->get();
    foreach ($payments as $p) {
        echo "id={$p->id} subscription_id={$p->subscription_id} checkout={$p->checkout_request_id} receipt={$p->mpesa_receipt} status={$p->status} business_id={$p->business_id} amount={$p->amount}\n";
    }

    echo "\nRECENT subscriptions (model query):\n";
    $subs = \App\Models\Subscription::where('business_id', $business->id)->orderBy('id','desc')->take(10)->get();
    foreach ($subs as $s) {
        echo "sub_id={$s->id} status={$s->status} transaction_id={$s->transaction_id} mpesa_receipt={$s->mpesa_receipt}\n";
    }

} catch (\Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

