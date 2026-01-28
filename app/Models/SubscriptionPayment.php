<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SubscriptionPayment extends Model
{
    // default table name (used if schema checks are not available yet)
    protected $table = 'subscriptions_payments';

    // cache resolved table name to avoid repeated schema checks
    protected static ?string $resolvedTable = null;

    protected $fillable = [
        'subscription_id',
        'business_id',
        'checkout_request_id',
        'merchant_request_id',
        'mpesa_receipt',
        'phone',
        'amount',
        'status',
        'metadata',
        'raw_response',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'raw_response' => 'array',
    ];

    public function getTable()
    {
        if (self::$resolvedTable) return self::$resolvedTable;

        try {
            if (Schema::hasTable('subscriptions_payments')) {
                self::$resolvedTable = 'subscriptions_payments';
            } elseif (Schema::hasTable('subscription_payments')) {
                self::$resolvedTable = 'subscription_payments';
            } else {
                // default to preferred name
                self::$resolvedTable = $this->table ?? 'subscriptions_payments';
            }
        } catch (\Throwable $_) {
            // If schema not available (during certain bootstrap phases), fall back to configured table
            self::$resolvedTable = $this->table ?? 'subscriptions_payments';
        }

        return self::$resolvedTable;
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    protected static function booted()
    {
        // When a subscription payment is saved and indicates a successful/completed payment,
        // ensure the corresponding subscription is reconciled and activated immediately.
        static::saved(function (SubscriptionPayment $sp) {
            // Only act on final/completed statuses
            if (! in_array($sp->status, ['completed', 'success'])) {
                return;
            }

            // Prefer mpesa_receipt but accept checkout_request_id as fallback
            $receipt = $sp->mpesa_receipt ?? null;
            $checkout = $sp->checkout_request_id ?? null;
            if (! $receipt && ! $checkout) {
                // Nothing to reconcile with
                return;
            }

            try {
                $subscription = null;

                // If linked directly, use it
                if ($sp->subscription_id) {
                    $subscription = Subscription::find($sp->subscription_id);
                }

                // If not found, try to resolve by checkout or receipt
                if (! $subscription) {
                    if ($receipt) {
                        $subscription = Subscription::where('transaction_id', $receipt)->first();
                    }
                    if (! $subscription && $checkout) {
                        $subscription = Subscription::where('transaction_id', $checkout)->first();
                    }
                }

                // Last-resort: try to find a pending subscription for the business with matching amount
                if (! $subscription && $sp->business_id) {
                    $subscription = Subscription::where('business_id', $sp->business_id)
                        ->where('amount', $sp->amount)
                        ->whereIn('status', ['initiated','pending','pending_verification'])
                        ->orderBy('created_at', 'desc')
                        ->first();
                }

                if (! $subscription) {
                    Log::info('SubscriptionPayment observer: could not resolve subscription', ['subscription_payment_id' => $sp->id, 'checkout' => $checkout, 'receipt' => $receipt]);
                    return;
                }

                // If subscription already active, nothing to do
                if ($subscription->status === 'active') {
                    Log::debug('SubscriptionPayment observer: subscription already active', ['subscription_id' => $subscription->id, 'subscription_payment_id' => $sp->id]);
                    return;
                }

                // Persist transaction id on subscription (prefer mpesa receipt)
                $tid = $receipt ?: $checkout;
                if ($tid) {
                    $subscription->transaction_id = $tid;
                }

                // Merge payment details
                $pd = $subscription->payment_details ?? [];
                $pd = is_array($pd) ? $pd : (is_object($pd) ? (array) $pd : []);
                $pd = array_merge($pd, [
                    'mpesa_receipt' => $receipt,
                    'checkout_request_id' => $checkout,
                    'reconciled_at' => now()->toDateTimeString(),
                ]);
                $subscription->payment_details = $pd;

                $subscription->save();

                // Immediately attempt activation (backend-driven) per requirement
                try {
                    $subscription->update([
                        'transaction_id' => $tid,
                        'mpesa_stk' => $receipt ?? $tid,
                        'mpesa_receipt' => $receipt ?? $subscription->mpesa_receipt ?? null,
                        'payment_details' => array_merge($subscription->payment_details ?? [], ['mpesa_receipt' => $receipt, 'checkout_request_id' => $checkout]),
                    ]);

                    if (method_exists($subscription->business, 'activateSubscription')) {
                        $subscription->business->activateSubscription($subscription, $tid, ['auto_reconcile' => true, 'source' => 'subscription_payment_observer']);
                        \Illuminate\Support\Facades\Log::info('SubscriptionPayment observer: subscription auto-activated', ['subscription_id' => $subscription->id, 'receipt' => $tid]);
                        try { \App\Services\SseService::pushBusinessEvent($subscription->business_id, 'subscription.activated', ['id' => $subscription->id, 'status' => 'active']); } catch (\Throwable $_) {}
                    } else {
                        $subscription->update(['status' => 'pending_verification']);
                        \Illuminate\Support\Facades\Log::warning('SubscriptionPayment observer: business activation helper missing', ['subscription_id' => $subscription->id]);
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('SubscriptionPayment observer: activation failed', ['error' => $e->getMessage(), 'subscription_id' => $subscription->id ?? null]);
                    try { $subscription->update(['status' => 'pending_verification']); } catch (\Throwable $_) {}
                }
            } catch (\Throwable $e) {
                Log::error('SubscriptionPayment observer exception', ['error' => $e->getMessage(), 'subscription_payment_id' => $sp->id]);
            }
        });

        static::created(function (SubscriptionPayment $sp) {
            try {
                $table = (self::$resolvedTable) ?? ($sp->getTable() ?? 'subscriptions_payments');
                \Illuminate\Support\Facades\Log::info('SubscriptionPayment created', ['id' => $sp->id, 'table' => $table, 'subscription_id' => $sp->subscription_id, 'checkout' => $sp->checkout_request_id, 'mpesa_receipt' => $sp->mpesa_receipt, 'status' => $sp->status, 'business_id' => $sp->business_id]);
            } catch (\Throwable $_) { /* ignore logging failures */ }
        });
    }
}
