<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Subscription;
use App\Models\MpesaPayment;

$user = User::where('email', 'admin@pos.com')->first();
if (! $user) { echo "NO_ADMIN_USER\n"; exit(1); }

auth()->loginUsingId($user->id);
$business = $user->currentBusiness ?? $user->businesses()->first();
if (! $business) { echo "NO_BUSINESS\n"; exit(1); }

// Create a pending_verification subscription
$subscription = Subscription::create([
    'business_id' => $business->id,
    'plan_id' => 1,
    'plan_name' => 'Test Plan',
    'amount' => 1.00,
    'currency' => $business->currency ?? 'KES',
    'status' => 'pending_verification',
    'payment_method' => 'MPESA_STK',
    'payment_details' => ['phone' => '254700000000', 'billing_cycle' => 'monthly'],
]);

echo "Created pending subscription id={$subscription->id}\n";

// Create successful MpesaPayment linking to SUB-<id>
$mp = MpesaPayment::create([
    'business_id' => $business->id,
    'sale_id' => null,
    'checkout_request_id' => 'CHK-' . strtoupper(uniqid()),
    'merchant_request_id' => 'MR-' . strtoupper(uniqid()),
    'phone' => '254700000000',
    'amount' => $subscription->amount,
    'reference' => null,
    'account_reference' => 'SUB-' . $subscription->id,
    'status' => 'success',
    'receipt' => 'RCT-' . strtoupper(uniqid()),
    'raw_response' => ['simulated'=>true],
]);

echo "Created MpesaPayment id={$mp->id} checkout={$mp->checkout_request_id} receipt={$mp->receipt}\n";

// Now call the controller index to trigger proactive activation
$ctrl = app(\App\Http\Controllers\Business\SubscriptionController::class);
try {
    $res = $ctrl->index();
    echo "Called SubscriptionController::index()\n";
} catch (\Throwable $e) {
    echo "Controller call failed: " . $e->getMessage() . "\n";
}

$sub = Subscription::find($subscription->id);
echo "After index: subscription status={$sub->status} starts_at={$sub->starts_at} ends_at={$sub->ends_at} transaction_id={$sub->transaction_id}\n";

// cleanup: remove created test records
// (Keep them for inspection if needed)

echo "Done\n";

