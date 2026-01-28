<?php
// scripts/auto_activate_from_payments.php
// One-off: find successful mpesa_payments with mpesa receipt and account_reference SUB-<id>
// Auto-activate corresponding subscriptions and mark subscription_payments completed.

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MpesaPayment;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Facades\Log;

$results = ['processed' => 0, 'activated' => 0, 'skipped' => 0, 'errors' => [], 'messages' => []];

// Only consider mpesa payments that include a receipt (finalized callback) and are marked success/completed
$payments = MpesaPayment::whereNotNull('receipt')
    ->whereIn('status', ['success', 'completed'])
    ->orderBy('created_at')
    ->get();

foreach ($payments as $p) {
    $results['processed']++;

    $checkout = $p->checkout_request_id ?? null;
    $receipt = $p->receipt ?? null;
    $acctRef = $p->account_reference ?? null;

    // Resolve subscription by several heuristics
    $subscription = null;

    // 1) account_reference SUB-<id>
    if ($acctRef && preg_match('/SUB-(\d+)/', $acctRef, $m)) {
        $sid = (int) $m[1];
        try { $subscription = Subscription::find($sid); } catch (\Throwable $_) { $subscription = null; }
    }

    // 2) try to find a SubscriptionPayment that matches this mpesa payment and use its subscription
    if (! $subscription) {
        try {
            $found = SubscriptionPayment::where(function($q) use ($checkout, $receipt) {
                if ($checkout) $q->orWhere('checkout_request_id', $checkout);
                if ($receipt) $q->orWhere('mpesa_receipt', $receipt);
            })->latest()->first();

            if ($found && $found->subscription_id) {
                $subscription = Subscription::find($found->subscription_id);
            }
        } catch (\Throwable $_) { /* ignore lookup errors */ }
    }

    // 3) try to match subscriptions by transaction_id == checkout_request_id or transaction_id == receipt
    if (! $subscription && ($checkout || $receipt)) {
        try {
            if ($checkout) $subscription = Subscription::where('transaction_id', $checkout)->first();
            if (! $subscription && $receipt) $subscription = Subscription::where('transaction_id', $receipt)->first();
        } catch (\Throwable $_) { $subscription = null; }
    }

    // 4) heuristic: same business_id + amount + a recent pending subscription
    if (! $subscription && isset($p->business_id) && isset($p->amount)) {
        try {
            $subscription = Subscription::where('business_id', $p->business_id)
                ->where('amount', $p->amount)
                ->whereIn('status', ['initiated','pending','pending_verification'])
                ->orderBy('created_at','desc')
                ->first();
        } catch (\Throwable $_) { $subscription = null; }
    }

    if (! $subscription) {
        $results['messages'][] = "Could not resolve subscription for mpesa_payment_id={$p->id} checkout={$checkout} receipt={$receipt} account_ref={$acctRef}";
        $results['skipped']++;
        continue;
    }

    // If subscription already active, skip
    if ($subscription->status === 'active') {
        $results['messages'][] = "Subscription {$subscription->id} already active, skipped (mpesa_payment_id={$p->id})";
        $results['skipped']++;
        continue;
    }

    // Ensure there is a SubscriptionPayment record for this subscription and mpesa payment
    try {
        $sp = SubscriptionPayment::where('subscription_id', $subscription->id)
            ->where(function($q) use ($checkout, $receipt) {
                if ($checkout) $q->orWhere('checkout_request_id', $checkout);
                if ($receipt) $q->orWhere('mpesa_receipt', $receipt);
            })->latest()->first();

        if (! $sp) {
            $sp = SubscriptionPayment::create([
                'subscription_id' => $subscription->id,
                'business_id' => $subscription->business_id,
                'checkout_request_id' => $checkout,
                'merchant_request_id' => $p->merchant_request_id ?? null,
                'mpesa_receipt' => $receipt,
                'phone' => $p->phone ?? ($subscription->payment_details['phone'] ?? null),
                'amount' => $p->amount ?? $subscription->amount,
                'status' => 'completed',
                'metadata' => array_merge($p->metadata ?? [], ['reconciled_from' => 'mpesa_payments', 'mpesa_payment_id' => $p->id]),
                'raw_response' => $p->toArray(),
            ]);
        } else {
            // update/mark completed and ensure mpesa_receipt stored
            $sp->mpesa_receipt = $sp->mpesa_receipt ?? $receipt;
            $sp->status = 'completed';
            $sp->metadata = array_merge($sp->metadata ?? [], ['reconciled_from' => 'mpesa_payments', 'mpesa_payment_id' => $p->id]);
            $sp->save();
        }
    } catch (\Throwable $e) {
        $results['errors'][] = ['mpesa_payment_id' => $p->id, 'error' => 'Failed to ensure SubscriptionPayment: ' . $e->getMessage()];
        continue;
    }

    // Persist subscription.transaction_id = mpesa_receipt (label the mpesa_STK)
    try {
        if (! empty($receipt)) {
            $subscription->transaction_id = $receipt;
        } elseif (! empty($checkout)) {
            // fallback if no receipt
            $subscription->transaction_id = $checkout;
        }

        // attach payment details info
        $pd = $subscription->payment_details ?? [];
        $pd = is_array($pd) ? $pd : (is_object($pd) ? (array)$pd : []);
        $pd = array_merge($pd, [
            'mpesa_receipt' => $receipt,
            'checkout_request_id' => $checkout,
            'reconciled_at' => now()->toDateTimeString(),
        ]);
        $subscription->payment_details = $pd;
        $subscription->save();
    } catch (\Throwable $e) {
        $results['errors'][] = ['subscription_id' => $subscription->id, 'error' => 'Failed to persist subscription transaction_id: ' . $e->getMessage()];
        continue;
    }

    // Activate subscription via business helper
    try {
        $subReceipt = $receipt ?: $checkout;
        $subscription->business->activateSubscription($subscription, $subReceipt, ['auto_reconcile' => true, 'source' => 'mpesa_reconcile_script']);
        $results['activated']++;
        $results['messages'][] = "Activated subscription {$subscription->id} using mpesa_payment {$p->id} receipt={$receipt}";

        // mark the SubscriptionPayment as reconciled in metadata
        try {
            $sp->metadata = array_merge($sp->metadata ?? [], ['auto_reconcile' => true, 'mpesa_payment_id' => $p->id]);
            $sp->save();
        } catch (\Throwable $_) {
            // non-fatal
        }

    } catch (\Throwable $e) {
        $results['errors'][] = ['subscription_id' => $subscription->id, 'error' => 'Activation failed: ' . $e->getMessage()];
        continue;
    }
}

// Print summary
echo json_encode($results, JSON_PRETTY_PRINT) . PHP_EOL;

