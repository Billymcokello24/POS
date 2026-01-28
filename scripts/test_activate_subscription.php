<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Log;

try {
    $user = \App\Models\User::first();
    if (! $user) { echo "no user\n"; exit(1); }
    // ensure we have a business and a plan
    $business = \App\Models\Business::first();
    $plan = \App\Models\Plan::first();
    if (! $business || ! $plan) { echo json_encode(['ok'=>false,'error'=>'missing business or plan']); exit(1); }

    // create a pending subscription
    $sub = \App\Models\Subscription::create([
        'business_id' => $business->id,
        'plan_id' => $plan->id,
        'plan_name' => $plan->name,
        'amount' => $plan->price_monthly ?? 100,
        'currency' => 'KES',
        'status' => 'pending',
        'payment_method' => 'MPESA_TILL',
        'payment_details' => ['billing_cycle'=>'monthly'],
    ]);

    echo "Created pending subscription id={$sub->id}\n";

    // simulate admin approve by calling business->activateSubscription
    $business->activateSubscription($sub, 'SIM-RECEIPT-'.uniqid(), ['test' => true]);

    $subFresh = \App\Models\Subscription::find($sub->id);
    $bizFresh = \App\Models\Business::find($business->id);

    echo "Subscription status: " . ($subFresh->status ?? 'null') . "\n";
    echo "Subscription transaction_id: " . ($subFresh->transaction_id ?? 'null') . "\n";
    echo "Business plan_id: " . ($bizFresh->plan_id ?? 'null') . "\n";
    echo "Business plan_ends_at: " . ($bizFresh->plan_ends_at ?? 'null') . "\n";

    // list feature pivots for this business
    $features = \DB::table('business_feature')->where('business_id', $business->id)->get();
    echo "Business features pivot rows: \n";
    foreach ($features as $f) {
        echo json_encode((array)$f) . "\n";
    }

    // active subscription check
    $active = $business->activeSubscription()->first();
    echo "Active subscription id via relation: " . ($active->id ?? 'none') . "\n";

    echo json_encode(['ok'=>true]);
} catch (\Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'trace'=>substr($e->getTraceAsString(),0,1000)]);
}

