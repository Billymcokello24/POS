<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\MpesaPayment;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Notifications\SubscriptionActivated;

class SubscriptionActivationService
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Finalize and activate a subscription based on a successful M-Pesa payment.
     * This is the canonical activation entry point for all paths (Callbacks, Confirmations, Reconciliation).
     */
    public function finalizeFromPayment(array $data): bool
    {
        $checkout = $data['checkout_request_id'] ?? null;
        $mpesaReceipt = $data['mpesa_receipt'] ?? null;
        $resultCode = $data['result_code'] ?? null;
        $phone = $data['phone'] ?? null;
        $amount = $data['amount'] ?? null;

        // 1. Resolve MpesaPayment (Financial Source of Truth)
        $mpesaPayment = MpesaPayment::where('checkout_request_id', $checkout)->first();
        if (!$mpesaPayment && $mpesaReceipt) {
            $mpesaPayment = MpesaPayment::where('receipt', $mpesaReceipt)->first();
        }

        if (!$mpesaPayment) {
            Log::warning('SubscriptionActivationService: MpesaPayment record not found', ['checkout' => $checkout, 'receipt' => $mpesaReceipt]);
            return false;
        }

        // Use mpesaPayment result_code if not passed explicitly
        if ($resultCode === null) {
            $resultCode = $mpesaPayment->result_code;
        }

        // 2. Resolve or Create Gated Subscription
        $subscription = null;
        if ($mpesaPayment->subscription_id) {
            $subscription = Subscription::find($mpesaPayment->subscription_id);
        }

        if (!$subscription && $checkout) {
            $subscription = Subscription::where('checkout_request_id', $checkout)->first();
        }

        Log::info('SubscriptionActivationService: Attempting finalization', [
            'checkout' => $checkout,
            'receipt' => $mpesaReceipt,
            'found_sub' => $subscription ? $subscription->id : 'none',
            'payment_status' => $mpesaPayment->status
        ]);

        // 3. Handle Payment Truth (Strict Gating - Point 1 & 9)
        if ($resultCode === null) {
            return false;
        }

        if ((int)$resultCode !== 0) {
            if ($subscription) {
                $subscription->update([
                    'status' => Subscription::STATUS_CANCELLED,
                    'is_active' => false,
                    'metadata' => array_merge($subscription->metadata ?? [], [
                        'failure_reason' => 'mpesa_non_zero_result',
                        'result_code' => $resultCode,
                        'failed_at' => now()->toDateTimeString(),
                        'truth_source' => 'mpesa_payments_record'
                    ])
                ]);
            }

            // Sync SubscriptionPayment ledger status if exists
            SubscriptionPayment::updateOrCreate(
                ['checkout_request_id' => $checkout ?? $mpesaReceipt],
                [
                    'subscription_id' => $subscription?->id,
                    'business_id' => $subscription?->business_id ?? $mpesaPayment->business_id,
                    'status' => 'failed',
                    'amount' => $mpesaPayment->amount,
                    'metadata' => array_merge($mpesaPayment->metadata ?? [], ['failed_at' => now()->toDateTimeString()])
                ]
            );

            return false;
        }

        // 4. Handle Success (ResultCode === 0)
        return DB::transaction(function () use ($subscription, $mpesaPayment, $mpesaReceipt, $checkout, $phone, $amount) {
            try {
                $isNewSubscription = false;

                if (!$subscription) {
                    // Point 6: Automated subscription creation after confirmed payment
                    $metadata = $mpesaPayment->metadata ?? [];

                    // If it's not a subscription payment intent, we don't auto-create here
                    if (($metadata['type'] ?? '') !== 'subscription' && empty($metadata['plan_id'])) {
                        // Try heuristic fallback if it's a known business
                        if (!$checkout && $mpesaReceipt) {
                            return $this->handleHeuristicActivation($mpesaPayment);
                        }
                        return false;
                    }

                    $planId = $metadata['plan_id'] ?? 1;
                    $plan = \App\Models\Plan::find($planId);
                    $planName = $plan ? $plan->name : 'Unknown Plan';

                    $subscription = Subscription::create([
                        'business_id' => $mpesaPayment->business_id,
                        'plan_id' => $planId,
                        'plan_name' => $planName,
                        'amount' => $mpesaPayment->amount,
                        'currency' => 'KES',
                        'status' => Subscription::STATUS_PENDING,
                        'payment_method' => 'MPESA_STK',
                        'checkout_request_id' => $checkout,
                        'payment_details' => [
                            'billing_cycle' => $metadata['billing_cycle'] ?? 'monthly',
                            'phone' => $mpesaPayment->phone,
                        ],
                    ]);

                    $isNewSubscription = true;
                }

                // Deterministic Activation (Idempotency - Point 13)
                if ($subscription->status === Subscription::STATUS_ACTIVE && !$isNewSubscription) {
                    return true;
                }

                $business = $subscription->business;

                // SaaS Blue-Print Point 1 & 9: AUTO-ACTIVATION ENFORCEMENT
                if ($business) {
                    $business->activateSubscription($subscription, $mpesaReceipt ?? $mpesaPayment->receipt);
                    $business->refresh();

                    // AUTO-ACTIVATE BUSINESS AND USER (for public subscriptions)
                    if (!$business->is_active) {
                        $business->update(['is_active' => true]);
                    }

                    // Activate the business owner/admin user
                    $owner = $business->owner;
                    if ($owner && !$owner->is_active) {
                        $owner->update(['is_active' => true]);
                        Log::info('Auto-activated business owner account', ['user_id' => $owner->id]);
                    }

                    // Send email with receipt
                    try {
                        $owner->notify(new SubscriptionActivated($subscription));
                        Log::info('Sent subscription activated email with receipt', [
                            'subscription_id' => $subscription->id,
                            'user_email' => $owner->email
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('Failed to send subscription email', ['error' => $e->getMessage()]);
                    }

                    Log::info('Subscription and Business activated via activateSubscription', [
                        'subscription_id' => $subscription->id,
                        'business_id' => $business->id,
                        'plan_id' => $business->plan_id,
                        'ends_at' => $business->plan_ends_at
                    ]);
                } else {
                    // Fallback if no business linked directly (rare)
                    $subscription->update([
                        'status' => Subscription::STATUS_ACTIVE,
                        'is_active' => true,
                        'is_verified' => true,
                        'mpesa_receipt' => $mpesaReceipt ?? $mpesaPayment->receipt,
                        'activated_at' => now(),
                        'payment_confirmed_at' => now(),
                    ]);
                }

                // Explicitly link the MpesaPayment record to the subscription
                if ($mpesaPayment) {
                    $mpesaPayment->update(['subscription_id' => $subscription->id]);
                }

                // Point 8: Admin visibility via SubscriptionPayment ledger
                SubscriptionPayment::updateOrCreate(
                    ['checkout_request_id' => $checkout ?? $mpesaPayment?->checkout_request_id ?? $subscription->checkout_request_id],
                    [
                        'subscription_id' => $subscription->id,
                        'business_id' => $subscription->business_id,
                        'business_name' => $business->name ?? 'Unknown Business',
                        'plan_id' => $subscription->plan_id,
                        'plan_name' => $subscription->plan_name,
                        'billing_cycle' => $subscription->payment_details['billing_cycle'] ?? 'monthly',
                        'status' => 'success',
                        'approval_status' => 'approved',
                        'mpesa_receipt' => $mpesaReceipt ?? $mpesaPayment?->receipt,
                        'transaction_id' => $mpesaReceipt ?? $mpesaPayment?->receipt,
                        'phone' => $phone ?? $mpesaPayment?->phone,
                        'amount' => $amount ?? $mpesaPayment?->amount,
                        'metadata' => array_merge($mpesaPayment->metadata ?? [], [
                            'finalized_at' => now()->toDateTimeString(),
                            'source' => 'subscription_activation_service_sync'
                        ])
                    ]
                );

                // Point 10: Real-time propagation
                try {
                    \App\Services\SseService::pushBusinessEvent($subscription->business_id, 'subscription.activated', [
                        'id' => $subscription->id,
                        'plan_name' => $subscription->plan_name,
                        'ends_at' => $subscription->ends_at
                    ]);
                } catch (\Throwable $_) {}

                // Notify Admins & Business with new system
                try {
                    // Notify all super admins
                    $this->notificationService->notifyNewSubscription($subscription);

                    // Check for duplicate subscriptions
                    $activeCount = $subscription->business->subscriptions()
                        ->where('status', Subscription::STATUS_ACTIVE)
                        ->count();

                    if ($activeCount > 1) {
                        $this->notificationService->notifyDuplicateSubscription($subscription);
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed to send notifications', ['error' => $e->getMessage()]);
                }

                return true;
            } catch (\Throwable $e) {
                Log::error('SubscriptionActivationService: Activation failed', ['error' => $e->getMessage()]);
                throw $e;
            }
        });
    }

    /**
     * Fallback for payments that don't have a direct "Intent" metadata.
     */
    protected function handleHeuristicActivation(MpesaPayment $mpesaPayment): bool
    {
        if (!$mpesaPayment->business_id) return false;

        $sub = Subscription::where('business_id', $mpesaPayment->business_id)
            ->where('amount', $mpesaPayment->amount)
            ->where('status', Subscription::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($sub) {
            $mpesaPayment->update(['subscription_id' => $sub->id]);
            return $this->finalizeFromPayment([
                'checkout_request_id' => $mpesaPayment->checkout_request_id,
                'mpesa_receipt' => $mpesaPayment->receipt,
            ]);
        }

        return false;
    }
}
