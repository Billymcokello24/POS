<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\MpesaPayment;
use App\Models\SubscriptionPayment;

// Use an admin user
$user = User::where('email', 'admin@pos.com')->first();
if (! $user) {
    echo "NO_ADMIN_USER\n";
    exit(1);
}

auth()->loginUsingId($user->id);

$plan = Plan::first();
if (! $plan) {
    echo "NO_PLAN_FOUND\n";
    exit(1);
}

// Choose a business (user current business or first business)
$business = $user->currentBusiness ?? $user->businesses()->first();
if (! $business) {
    echo "NO_BUSINESS\n";
    exit(1);
}

// Create an initiated subscription attached to this plan
$subscription = Subscription::create([
    'business_id' => $business->id,
    'plan_id' => $plan->id,
    'plan_name' => $plan->name,
    'amount' => $plan->price_monthly ?: 1.00,
    'currency' => $business->currency ?? 'KES',
    'status' => 'initiated',
    'payment_method' => 'MPESA_STK',
    'payment_details' => ['phone' => '254700000000', 'billing_cycle' => 'monthly'],
]);

echo "Created initiated subscription id={$subscription->id} plan_id={$plan->id}\n";

$checkout = 'SIM-ACT-' . strtoupper(uniqid());
$receipt = 'RCT-ACT-' . strtoupper(uniqid());

$mp = MpesaPayment::create([
    'business_id' => $business->id,
    'sale_id' => null,
    'checkout_request_id' => $checkout,
    'merchant_request_id' => 'MR-' . strtoupper(uniqid()),
    'phone' => $subscription->payment_details['phone'],
    'amount' => $subscription->amount,
    'reference' => null,
    'account_reference' => 'SUB-' . $subscription->id,
    'status' => 'success',
    'receipt' => $receipt,
    'raw_response' => ['simulated' => true],
]);

$sp = SubscriptionPayment::create([
    'subscription_id' => $subscription->id,
    'business_id' => $business->id,
    'checkout_request_id' => $checkout,
    'merchant_request_id' => $mp->merchant_request_id,
    'mpesa_receipt' => $receipt,
    'phone' => $mp->phone,
    'amount' => $mp->amount,
    'status' => 'completed',
    'metadata' => ['simulated' => true],
    'raw_response' => ['simulated' => true],
]);

echo "Created MpesaPayment checkout={$checkout} receipt={$receipt} and SubscriptionPayment id={$sp->id}\n";

// Call controller finalize
$ctrl = app(\App\Http\Controllers\Api\SubscriptionPaymentController::class);
$req = new Illuminate\Http\Request(['subscription_id' => $subscription->id, 'checkout_request_id' => $checkout, 'mpesa_receipt' => $receipt], [], [], [], [], ['HTTP_ACCEPT' => 'application/json','HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
$res = $ctrl->finalize($req);

if (method_exists($res, 'getContent')) {
    echo "Controller response:\n" . $res->getContent() . "\n";
} else {
    var_export($res);
}

$sub2 = Subscription::find($subscription->id);
echo "Subscription status={$sub2->status} starts_at={$sub2->starts_at} ends_at={$sub2->ends_at} transaction_id={$sub2->transaction_id}\n";
$biz = $business->fresh();
echo "Business plan_id={$biz->plan_id}\n";

// Check features pivot count
$enabled = $biz->features()->wherePivot('is_enabled', true)->pluck('features.id')->toArray();
echo "Enabled feature ids: " . json_encode($enabled) . "\n";

echo "DONE\n";

