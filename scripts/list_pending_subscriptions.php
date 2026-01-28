<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\MpesaPayment;

$pending = Subscription::where('status', 'pending_verification')->orderBy('id','desc')->get();

if ($pending->isEmpty()) {
    echo "No subscriptions with status 'pending_verification' found.\n";
    exit(0);
}

foreach ($pending as $p) {
    $business = $p->business()->first();
    echo "SUB#{$p->id} business_id={$p->business_id} business_name=" . ($business->name ?? 'N/A') . " plan_id={$p->plan_id} plan_name='{$p->plan_name}' status={$p->status} transaction_id={$p->transaction_id} created_at={$p->created_at}\n";
    $sps = SubscriptionPayment::where('subscription_id', $p->id)->get();
    foreach ($sps as $sp) {
        echo "  SP id={$sp->id} checkout={$sp->checkout_request_id} mpesa_receipt={$sp->mpesa_receipt} status={$sp->status} amount={$sp->amount}\n";
    }
    $mps = MpesaPayment::where('account_reference', 'SUB-'.$p->id)->orWhere('checkout_request_id', $p->transaction_id)->get();
    foreach ($mps as $mp) {
        echo "  MP id={$mp->id} checkout={$mp->checkout_request_id} receipt={$mp->receipt} status={$mp->status} amount={$mp->amount}\n";
    }
    echo "---\n";
}

