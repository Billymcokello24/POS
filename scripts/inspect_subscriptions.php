<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$user = User::where('email', 'admin@pos.com')->first();
if (! $user) { echo "NO_ADMIN_USER\n"; exit(1); }

auth()->loginUsingId($user->id);

$business = $user->currentBusiness ?? $user->businesses()->first();
if (! $business) { echo "NO_BUSINESS\n"; exit(1); }

echo "Business: {$business->id} - {$business->name}\n";

$subs = $business->subscriptions()->orderBy('id','desc')->get();
foreach ($subs as $s) {
    echo "SUB#{$s->id} status={$s->status} plan_id={$s->plan_id} plan_name={$s->plan_name} starts_at={$s->starts_at} ends_at={$s->ends_at} transaction_id={$s->transaction_id}\n";
}

$active = $business->activeSubscription()->first();
if ($active) {
    echo "Active subscription: #{$active->id} plan={$active->plan_name} starts={$active->starts_at} ends={$active->ends_at}\n";
} else {
    echo "No active subscription\n";
}

$pending = $business->subscriptions()->where('status','pending_verification')->get();
if ($pending->count()>0) {
    echo "Pending verification subscriptions:\n";
    foreach ($pending as $p) {
        echo " - #{$p->id} plan={$p->plan_name} created={$p->created_at}\n";
    }
} else {
    echo "No pending_verification subscriptions\n";
}

