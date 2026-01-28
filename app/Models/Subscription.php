<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'plan_id',
        'plan_name',
        'amount',
        'currency',
        'status',
        'transaction_id',
        'payment_method',
        'payment_details',
        'starts_at',
        'ends_at',
        'mpesa_receipt',
        'mpesa_stk',
        'verified_at',
        'activated_at',
        'checkout_request_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'amount' => 'decimal:2',
        'payment_details' => 'array',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive()
    {
        return $this->status === 'active' && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    protected static function booted()
    {
        static::created(function (Subscription $subscription) {
            // When a subscription is created in an initial state, ensure a ledger row exists so admin can verify it.
            try {
                if (! in_array($subscription->status, ['initiated', 'pending', 'pending_verification', 'pending'])) {
                    return;
                }

                // If a SubscriptionPayment already exists for this subscription, do nothing
                if (class_exists(\App\Models\SubscriptionPayment::class)) {
                    $exists = \App\Models\SubscriptionPayment::where('subscription_id', $subscription->id)->exists();
                    if ($exists) return;

                    \App\Models\SubscriptionPayment::create([
                        'subscription_id' => $subscription->id,
                        'business_id' => $subscription->business_id,
                        'checkout_request_id' => $subscription->checkout_request_id ?? $subscription->transaction_id ?? null,
                        'merchant_request_id' => null,
                        'mpesa_receipt' => $subscription->mpesa_receipt ?? null,
                        'phone' => $subscription->payment_details['phone'] ?? null,
                        'amount' => $subscription->amount,
                        'status' => $subscription->status === 'pending' ? 'pending' : 'pending',
                        'metadata' => ['auto_created_from_subscription' => true],
                        'raw_response' => null,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to create SubscriptionPayment from Subscription created event', ['error' => $e->getMessage(), 'subscription_id' => $subscription->id]);
            }
        });
    }
}
