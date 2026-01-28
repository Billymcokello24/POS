<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Subscription;
use App\Models\SubscriptionPayment;

$sub = Subscription::whereIn('status', ['initiated','pending','pending_verification'])->first();
if (! $sub) {
    echo "No pending subscription found to test\n";
    exit(1);
}

echo "Found pending subscription id={$sub->id} status={$sub->status} amount={$sub->amount}\n";

$receipt = 'TEST-RECEIPT-'.strtoupper(uniqid());

$sp = SubscriptionPayment::create([
    'subscription_id' => $sub->id,
    'business_id' => $sub->business_id,
    'checkout_request_id' => 'TESTCHK-'.strtoupper(uniqid()),
    'merchant_request_id' => null,
    'mpesa_receipt' => $receipt,
    'phone' => '254700000000',
    'amount' => $sub->amount,
    'status' => 'completed',
    'metadata' => ['simulated' => true],
    'raw_response' => [],
]);

echo "Created SubscriptionPayment id={$sp->id} mpesa_receipt={$sp->mpesa_receipt}\n";

$subFresh = $sub->fresh();

echo "After observer: subscription id={$subFresh->id} status={$subFresh->status} transaction_id={$subFresh->transaction_id}\n";

if ($subFresh->status === 'active') {
    echo "SUCCESS: subscription auto-activated by observer.\n";
} else {
    echo "FAIL: subscription not activated.\n";
}

