<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class MpesaPayment extends Model
{
    use HasFactory;

    protected $table = 'mpesa_payments';

    protected $casts = [
        'metadata' => 'array',
        'raw_response' => 'array',
    ];

    protected $fillable = [
        'business_id',
        'sale_id',
        'checkout_request_id',
        'merchant_request_id',
        'receipt',
        'phone',
        'amount',
        'reference',
        'account_reference',
        'status',
        'metadata',
        'raw_response',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    protected static function booted()
    {
        static::saved(function (MpesaPayment $mp) {
            // Only act on finalized receipts
            if (! in_array($mp->status, ['success', 'completed'])) return;
            $receipt = $mp->receipt ?? null;
            if (! $receipt) return;

            try {
                // try to resolve subscription id from account_reference SUB-<id>
                $acct = $mp->account_reference ?? null;
                $subscriptionId = null;
                if ($acct && preg_match('/SUB-(\d+)/', $acct, $m)) {
                    $subscriptionId = (int) $m[1];
                }

                // if we found id, dispatch activation job
                if ($subscriptionId) {
                    \App\Jobs\AttemptSubscriptionActivation::dispatch($subscriptionId, $receipt, ['source' => 'mpesa_payment_observer']);
                    Log::info('MpesaPayment observer: dispatched activation job', ['mpesa_payment_id' => $mp->id, 'subscription_id' => $subscriptionId, 'receipt' => $receipt]);
                    return;
                }

                // fallback: attempt to find a subscription_payment linking this receipt
                $found = \App\Models\SubscriptionPayment::where('mpesa_receipt', $receipt)->first();
                if ($found && $found->subscription_id) {
                    \App\Jobs\AttemptSubscriptionActivation::dispatch($found->subscription_id, $receipt, ['source' => 'mpesa_payment_observer']);
                    Log::info('MpesaPayment observer: dispatched activation job via subscription_payment', ['mpesa_payment_id' => $mp->id, 'subscription_payment_id' => $found->id, 'receipt' => $receipt]);
                    return;
                }

                // last-resort: find a pending subscription for this business and amount
                if ($mp->business_id) {
                    $sub = \App\Models\Subscription::where('business_id', $mp->business_id)
                        ->where('amount', $mp->amount)
                        ->whereIn('status', ['initiated','pending','pending_verification'])
                        ->orderBy('created_at', 'desc')
                        ->first();
                    if ($sub) {
                        \App\Jobs\AttemptSubscriptionActivation::dispatch($sub->id, $receipt, ['source' => 'mpesa_payment_observer']);
                        Log::info('MpesaPayment observer: dispatched activation job via business/amount heuristic', ['mpesa_payment_id' => $mp->id, 'subscription_id' => $sub->id, 'receipt' => $receipt]);
                        return;
                    }
                }
            } catch (\Throwable $e) {
                Log::error('MpesaPayment observer exception', ['error' => $e->getMessage(), 'mpesa_payment_id' => $mp->id]);
            }
        });
    }
}
