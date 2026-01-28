<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Plan;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;

// create or get Plan
$plan = Plan::first() ?? Plan::create(["name" => "Test Plan", "max_products" => 0, "max_users" => 0]);

// create Business
$business = Business::create(["name" => "Sim Test Business", "currency" => "KES"]);

// create subscription pending
$subscription = Subscription::create([
    'business_id' => $business->id,
    'plan_id' => $plan->id,
    'plan_name' => $plan->name,
    'amount' => 100,
    'currency' => 'KES',
    'status' => 'pending',
    'payment_method' => 'MPESA_STK',
    'payment_details' => ['phone' => '254700000000', 'billing_cycle' => 'monthly'],
]);

$checkout = 'SIM-CHK-' . strtoupper(uniqid());
$receipt = 'SIM-RCT-' . strtoupper(uniqid());

// create SubscriptionPayment
$sp = SubscriptionPayment::create([
    'subscription_id' => $subscription->id,
    'business_id' => $business->id,
    'checkout_request_id' => $checkout,
    'merchant_request_id' => 'MR-' . strtoupper(uniqid()),
    'mpesa_receipt' => $receipt,
    'phone' => '254700000000',
    'amount' => 100,
    'status' => 'completed',
    'metadata' => ['simulated' => true],
    'raw_response' => ['simulated' => true],
]);

echo "Created subscription id={$subscription->id} checkout={$checkout} receipt={$receipt}\n";

$ctrl = app(\App\Http\Controllers\Api\SubscriptionPaymentController::class);
$ok = $ctrl->finalizeFromCallback(['checkout_request_id' => $checkout, 'mpesa_receipt' => $receipt, 'phone' => '254700000000', 'amount' => 100]);

echo "finalizeFromCallback returned: " . ($ok ? 'true' : 'false') . "\n";

$sub2 = Subscription::find($subscription->id);
echo "Subscription status={$sub2->status} transaction_id={$sub2->transaction_id} mpesa_receipt={$sub2->mpesa_receipt} activated_at={$sub2->activated_at}\n";

$rows = SubscriptionPayment::where('subscription_id', $subscription->id)->get();
echo "SubscriptionPayment rows: count=" . $rows->count() . "\n";
foreach ($rows as $r) {
    echo "- id={$r->id} checkout={$r->checkout_request_id} mpesa_receipt={$r->mpesa_receipt} status={$r->status}\n";
}

echo "DONE\n";

