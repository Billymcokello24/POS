<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Subscription;
use App\Models\Business;

$business = Business::first();
if (! $business) {
    echo "NO_BUSINESS\n";
    exit(1);
}

try {
    $sub = Subscription::create([
        'business_id' => $business->id,
        'plan_id' => $business->plan_id ?? 1,
        'plan_name' => 'TMP-TEST',
        'amount' => 1.00,
        'currency' => $business->currency ?? 'KES',
        'status' => 'pending',
        'payment_method' => 'MPESA_STK',
        'payment_details' => ['phone' => '254700000000', 'billing_cycle' => 'monthly'],
    ]);
    echo "CREATED:" . $sub->id . "\n";
} catch (\Throwable $e) {
    echo "ERROR:" . $e->getMessage() . "\n";
    exit(1);
}

