<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

$user = User::where('email', 'admin@pos.com')->first();
if (! $user) { echo "NO_ADMIN_USER\n"; exit(1); }

auth()->loginUsingId($user->id);

$subs = Subscription::whereIn('status', ['initiated','pending','pending_verification'])->get();
if ($subs->isEmpty()) {
    echo "No subscriptions in initiated/pending/pending_verification state.\n";
    exit(0);
}

foreach ($subs as $s) {
    echo "Attempting activation for subscription id={$s->id} business_id={$s->business_id} status={$s->status}\n";
    try {
        if (! method_exists($s->business, 'activateSubscription')) {
            echo "  business->activateSubscription not available\n";
            continue;
        }
        $tx = $s->transaction_id ?? ($s->payment_details['checkout'] ?? null) ?? ('FORCE-' . strtoupper(uniqid()));
        $s->business->activateSubscription($s, $tx, ['source' => 'force_script']);
        $fresh = Subscription::find($s->id);
        echo "  After activation: status={$fresh->status} starts_at={$fresh->starts_at} ends_at={$fresh->ends_at} transaction_id={$fresh->transaction_id}\n";
    } catch (\Throwable $e) {
        echo "  Activation failed: " . $e->getMessage() . "\n";
        Log::error('Force activation script failed', ['subscription_id' => $s->id, 'error' => $e->getMessage()]);
    }
}

echo "Done\n";

