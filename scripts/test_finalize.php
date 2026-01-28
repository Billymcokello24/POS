<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Subscription;
use App\Models\MpesaPayment;
use App\Models\SubscriptionPayment;
use App\Models\User;

$user = User::where('email', 'admin@pos.com')->first();
if (! $user) {
    echo "NO_ADMIN_USER\n";
    exit(1);
}

auth()->loginUsingId($user->id);

$sub = Subscription::whereIn('status', ['initiated','pending'])->latest()->first();
if (! $sub) {
    echo "NO_SUBSCRIPTION_FOUND\n";
    exit(1);
}

echo "Found subscription id={$sub->id} status={$sub->status}\n";

$checkout = 'SIM-TEST-' . strtoupper(uniqid());
$receipt = 'RCT-' . strtoupper(uniqid());

// create MpesaPayment
$mp = MpesaPayment::create([
    'business_id' => $sub->business_id,
    'sale_id' => null,
    'checkout_request_id' => $checkout,
    'merchant_request_id' => 'MR-' . strtoupper(uniqid()),
    'phone' => $sub->payment_details['phone'] ?? null,
    'amount' => $sub->amount,
    'reference' => null,
    'account_reference' => 'SUB-' . $sub->id,
    'status' => 'success',
    'receipt' => $receipt,
    'raw_response' => ['simulated' => true],
]);

// create SubscriptionPayment
$sp = SubscriptionPayment::create([
    'subscription_id' => $sub->id,
    'business_id' => $sub->business_id,
    'checkout_request_id' => $checkout,
    'merchant_request_id' => $mp->merchant_request_id,
    'mpesa_receipt' => $receipt,
    'phone' => $mp->phone,
    'amount' => $mp->amount,
    'status' => 'completed',
    'metadata' => ['simulated' => true],
    'raw_response' => ['simulated' => true],
]);

echo "Created MpesaPayment checkout={$checkout} receipt={$receipt}\n";

// Call finalize via controller
$ctrl = app(\App\Http\Controllers\Api\SubscriptionPaymentController::class);
$req = new Illuminate\Http\Request([ 'subscription_id' => $sub->id, 'checkout_request_id' => $checkout, 'mpesa_receipt' => $receipt ], [], [], [], [], [ 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest' ]);
$res = $ctrl->finalize($req);

if (method_exists($res, 'getContent')) {
    echo "Controller response: \n" . $res->getContent() . "\n";
} else {
    echo "Controller response: (no getContent)\n";
    var_export($res);
}

$sub2 = Subscription::find($sub->id);
echo "Subscription now status={$sub2->status} transaction_id={$sub2->transaction_id}\n";

echo "SubscriptionPayment rows for this subscription:\n";
$rows = SubscriptionPayment::where('subscription_id', $sub->id)->get()->toArray();
print_r($rows);

echo "MpesaPayment rows for this checkout:\n";
print_r(MpesaPayment::where('checkout_request_id', $checkout)->get()->toArray());

echo "DONE\n";

