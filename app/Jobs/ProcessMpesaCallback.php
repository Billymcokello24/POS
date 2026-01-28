<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMpesaCallback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $callback;

    public function __construct(array $callback)
    {
        $this->callback = $callback;
    }

    public function handle()
    {
        $callbackData = $this->callback;
        $stk = $callbackData['Body']['stkCallback'] ?? null;

        // New: authoritative simple auto-activation path based on user's spec
        if ($stk) {
            $resultCode = $stk['ResultCode'] ?? null;
            $checkoutRequestId = $stk['CheckoutRequestID'] ?? null;
            $callbackMetadata = $stk['CallbackMetadata']['Item'] ?? [];

            $mpesaReceiptNumber = null;
            $phoneNumber = null;
            $amount = null;

            foreach ($callbackMetadata as $item) {
                if (!is_array($item)) continue;
                if (($item['Name'] ?? '') === 'MpesaReceiptNumber') $mpesaReceiptNumber = $item['Value'] ?? null;
                if (($item['Name'] ?? '') === 'PhoneNumber') $phoneNumber = $item['Value'] ?? null;
                if (($item['Name'] ?? '') === 'Amount') $amount = $item['Value'] ?? null;
            }

            if ($resultCode === 0 && $mpesaReceiptNumber && $checkoutRequestId) {
                // Find pending subscription by checkout_request_id
                try {
                    $subscription = \App\Models\Subscription::where('checkout_request_id', $checkoutRequestId)
                        ->whereIn('status', ['initiated','pending','pending_verification'])
                        ->first();

                    if ($subscription) {
                        // Prefer to use Business::activateSubscription to centralize activation logic
                        try {
                            $business = \App\Models\Business::find($subscription->business_id);

                            $metadata = [
                                'mpesa_receipt' => $mpesaReceiptNumber,
                                'phone' => $phoneNumber,
                                'amount' => $amount,
                                'transaction_date' => now()->toDateTimeString(),
                                'raw_callback' => $stk,
                            ];

                            if ($business && method_exists($business, 'activateSubscription')) {
                                // Use business method to activate and sync features/plan
                                $business->activateSubscription($subscription, $mpesaReceiptNumber, $metadata);
                            } else {
                                // Fallback to manual update if Business activation helper not available
                                $endsAt = $subscription->payment_details['billing_cycle'] ?? $subscription->billing_cycle ?? 'monthly';
                                $endsAt = $endsAt === 'yearly' ? now()->addYear() : now()->addMonth();

                                $subscription->update([
                                    'mpesa_receipt' => $mpesaReceiptNumber,
                                    'transaction_id' => $mpesaReceiptNumber,
                                    'status' => 'active',
                                    'verified_at' => now(),
                                    'activated_at' => now(),
                                    'ends_at' => $endsAt,
                                    'payment_details' => array_merge($subscription->payment_details ?? [], $metadata),
                                ]);

                                try {
                                    if ($business) {
                                        $business->plan_id = $subscription->plan_id;
                                        $business->plan_ends_at = $subscription->ends_at;
                                        $business->save();
                                    }
                                } catch (\Throwable $_) { /* ignore */ }
                            }

                            // Log the auto-activation
                            try {
                                // Use structured logging; fallback if activity() helper not available
                                Log::info('Subscription auto-activated via M-Pesa STK', [
                                    'subscription_id' => $subscription->id,
                                    'mpesa_receipt' => $mpesaReceiptNumber,
                                    'auto_verified' => true,
                                    'amount' => $amount,
                                    'caused_by' => $business->user->id ?? null,
                                ]);
                            } catch (\Throwable $_) { /* ignore */ }

                            // Store payment verification in payments table if you have one
                            try {
                                if (class_exists('App\\Models\\Payment')) {
                                    $p = new \App\Models\Payment();
                                    $p->subscription_id = $subscription->id;
                                    $p->business_id = $subscription->business_id;
                                    $p->amount = $amount;
                                    $p->mpesa_receipt = $mpesaReceiptNumber;
                                    $p->phone_number = $phoneNumber;
                                    $p->status = 'completed';
                                    $p->payment_method = 'stk';
                                    $p->verified_at = now();
                                    $p->reference_number = $mpesaReceiptNumber;
                                    $p->save();
                                }
                            } catch (\Throwable $_) { /* ignore */ }

                            return;
                        } catch (\Throwable $_) {
                            // ignore and continue to broader processing
                        }
                    }
                } catch (\Throwable $_) {
                    // ignore and continue to broader processing
                }
            }
        }

        $stk = $this->callback['Body']['stkCallback'] ?? null;
        if (!$stk) return;

        $checkoutId = $stk['CheckoutRequestID'] ?? null;
        $resultCode = $stk['ResultCode'] ?? null;

        // We will not insert into mpesa_payments here; focus on subscription_payments only.
        $payment = null;

         // Parse metadata/AccountReference if provided to resolve business
         $accountRef = null;
         if (isset($stk['CallbackMetadata']['Item'])) {
             $items = collect($stk['CallbackMetadata']['Item'])->pluck('Value', 'Name');
             $accountRef = $items['AccountReference'] ?? ($items['Reference'] ?? null);
         }

         // If accountRef follows SUB-{subscriptionId}, try to resolve subscription by id
         $subscription = null;
         if ($accountRef && preg_match('/^SUB-(\d+)/', $accountRef, $m)) {
             $subId = (int) $m[1];
             // broaden statuses to include pending_verification
             $subscription = \App\Models\Subscription::where('id', $subId)->whereIn('status', ['initiated','pending','pending_verification'])->first();
             // if still not found, try without status restriction (defensive)
             if (! $subscription) {
                 $subscription = \App\Models\Subscription::find($subId);
             }
         }

         // If payment not found by checkout id, try account_reference field pattern BIZ-{id}-...
        // No mpesa_payments lookup here: skip and proceed to reconcile via subscription_payments

         // Check if this is a subscription payment (independent from sales)
         if ($checkoutId) {
             // First try to find a SubscriptionPayment by checkout id
             try {
                 $subPayment = \App\Models\SubscriptionPayment::where('checkout_request_id', $checkoutId)->first();
                 if ($subPayment) {
                     $subscription = \App\Models\Subscription::find($subPayment->subscription_id);
                 }
             } catch (\Throwable $_) {
                 // ignore
             }

            // Next, try to derive subscription from account reference 'SUB-<id>' or MpesaPayment account_reference
            if (! $subscription && $accountRef) {
                if (preg_match('/^SUB-(\d+)/', $accountRef, $m)) {
                    $sid = (int) $m[1];
                    $subscription = \App\Models\Subscription::where('id', $sid)->whereIn('status', ['initiated','pending','pending_verification'])->first();
                }
            }

            // Fallback: try matching by transaction_id equal to checkout id
            if (! $subscription) {
                $subscription = \App\Models\Subscription::where('transaction_id', $checkoutId)->whereIn('status', ['initiated','pending'])->first();
            }
        }

        // We intentionally do NOT create mpesa_payments here. Subscription payment records are authoritative for subscription activation.
        if (!$checkoutId) {
            Log::info('ProcessMpesaCallback: no checkout id present in STK callback', ['callback' => $this->callback]);
        }

        if ($resultCode !== 0) {
            // nothing to update in mpesa_payments; ensure we mark subscription as failed if available
             // Also fail subscription if found
             if ($subscription) {
                 $subscription->update(['status' => 'failed', 'payment_details' => array_merge($subscription->payment_details ?? [], ['failure_reason' => $stk['ResultDesc'] ?? null])]);
             }
             return;
         }

         // Extract callback metadata items
         $items = collect($stk['CallbackMetadata']['Item'])->pluck('Value', 'Name');

        // Ensure a SubscriptionPayment exists for this callback so the observer can reconcile the subscription.
        try {
            $mpesaReceipt = $items['MpesaReceiptNumber'] ?? null;
            $amount = $items['Amount'] ?? null;
            $phone = $items['PhoneNumber'] ?? null;

            // Robustly resolve subscription if possible
            $resolvedSubscription = $subscription ?? null;
            if (! $resolvedSubscription) {
                // 1) If AccountReference contains SUB-<id>
                if ($accountRef && preg_match('/^SUB-(\d+)/', (string)$accountRef, $m)) {
                    $resolvedSubscription = \App\Models\Subscription::find((int)$m[1]);
                }

                // 2) Try by checkout id or mpesa receipt on subscriptions
                if (! $resolvedSubscription && $checkoutId) {
                    $resolvedSubscription = \App\Models\Subscription::where(function($q) use ($checkoutId, $mpesaReceipt) {
                        $q->where('checkout_request_id', $checkoutId)->orWhere('transaction_id', $checkoutId);
                        if ($mpesaReceipt) $q->orWhere('transaction_id', $mpesaReceipt)->orWhere('checkout_request_id', $mpesaReceipt);
                    })->first();
                }

                // 3) If still not found, try to find a recent pending subscription for a business that matches amount
                if (! $resolvedSubscription && $amount) {
                    $found = \App\Models\Subscription::where('amount', $amount)
                        ->whereIn('status', ['initiated','pending','pending_verification'])
                        ->orderBy('created_at', 'desc')
                        ->first();
                    if ($found) $resolvedSubscription = $found;
                }
            }

            $subPaymentQuery = \App\Models\SubscriptionPayment::query();
            if (! empty($checkoutId)) $subPaymentQuery->orWhere('checkout_request_id', $checkoutId);
            if (! empty($mpesaReceipt)) $subPaymentQuery->orWhere('mpesa_receipt', $mpesaReceipt);
            $existingSubPayment = $subPaymentQuery->latest()->first();

            $businessId = $resolvedSubscription->business_id ?? null;

            if ($existingSubPayment) {
                $existingSubPayment->update([
                    'subscription_id' => $resolvedSubscription->id ?? $existingSubPayment->subscription_id,
                    'business_id' => $businessId ?? $existingSubPayment->business_id,
                    'mpesa_receipt' => $mpesaReceipt ?? $existingSubPayment->mpesa_receipt,
                    'phone' => $phone ?? $existingSubPayment->phone,
                    'amount' => $amount ?? $existingSubPayment->amount,
                    'status' => $resultCode === 0 ? 'completed' : ($existingSubPayment->status ?? 'pending'),
                    'raw_response' => $this->callback,
                    'metadata' => array_merge($existingSubPayment->metadata ?? [], ['callback' => $stk, 'transaction_date' => $items['TransactionDate'] ?? null]),
                ]);
            } else {
                // If we cannot determine at least subscription_id OR business_id, log and skip to avoid DB constraint errors
                if (empty($resolvedSubscription) && empty($businessId)) {
                    Log::warning('ProcessMpesaCallback: cannot create subscription_payment - subscription and business unknown', ['checkout' => $checkoutId, 'receipt' => $mpesaReceipt, 'account_reference' => $accountRef]);
                } else {
                    try {
                        \App\Models\SubscriptionPayment::create([
                            'subscription_id' => $resolvedSubscription->id ?? null,
                            'business_id' => $businessId ?? null,
                            'checkout_request_id' => $checkoutId,
                            'merchant_request_id' => $stk['MerchantRequestID'] ?? null,
                            'mpesa_receipt' => $mpesaReceipt,
                            'phone' => $phone,
                            'amount' => $amount,
                            'status' => $resultCode === 0 ? 'completed' : 'pending',
                            'metadata' => ['callback' => $stk, 'transaction_date' => $items['TransactionDate'] ?? null, 'account_reference' => $accountRef],
                            'raw_response' => $this->callback,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('ProcessMpesaCallback: failed to persist subscription_payment', ['error' => $e->getMessage(), 'checkout' => $checkoutId, 'receipt' => $mpesaReceipt]);
                    }
                }
            }
        } catch (\Throwable $_) {
            // Non-fatal: continue
        }

        // If subscription not resolved yet, attempt to find it via SubscriptionPayment entries
        if (! $subscription) {
            try {
                $mpesaReceipt = $items['MpesaReceiptNumber'] ?? null;
                $found = \App\Models\SubscriptionPayment::where(function($q) use ($checkoutId, $mpesaReceipt, $accountRef) {
                    if ($checkoutId) $q->orWhere('checkout_request_id', $checkoutId);
                    if ($mpesaReceipt) $q->orWhere('mpesa_receipt', $mpesaReceipt);
                    if ($accountRef) $q->orWhere('checkout_request_id', $accountRef)->orWhere('mpesa_receipt', $accountRef);
                })->latest()->first();

                if ($found && $found->subscription_id) {
                    $subscription = \App\Models\Subscription::where('id', $found->subscription_id)->whereIn('status', ['initiated','pending'])->first();
                }
            } catch (\Throwable $_) {
                // ignore lookup errors
            }
        }

        // Handle subscription if found
        if ($subscription) {
            $mpesaReceipt = $items['MpesaReceiptNumber'] ?? null;
            $amount = $items['Amount'] ?? null;
            $phone = $items['PhoneNumber'] ?? null;
            $transactionDate = $items['TransactionDate'] ?? null;

            // Update or create SubscriptionPayment
            $subPayment = \App\Models\SubscriptionPayment::where('checkout_request_id', $checkoutId)
                ->orWhere('mpesa_receipt', $mpesaReceipt)
                ->first();

            if ($subPayment) {
                $subPayment->update([
                    'mpesa_receipt' => $mpesaReceipt ?? $subPayment->mpesa_receipt,
                    'phone' => $phone ?? $subPayment->phone,
                    'amount' => $amount ?? $subPayment->amount,
                    'status' => 'completed',
                    'raw_response' => $this->callback,
                    'metadata' => array_merge($subPayment->metadata ?? [], ['transaction_date' => $transactionDate, 'callback' => $stk]),
                ]);
            } else {
                // if subscription exists, link to it; if subscription is null but accountRef points to SUB-<id>, try to find again defensively
                if (! $subscription && $accountRef && preg_match('/^SUB-(\d+)/', $accountRef, $m2)) {
                    $s2 = (int) $m2[1];
                    $subscription = \App\Models\Subscription::find($s2);
                }

                \App\Models\SubscriptionPayment::create([
                    'subscription_id' => $subscription->id ?? null,
                    'business_id' => $subscription->business_id ?? null,
                    'checkout_request_id' => $checkoutId,
                    'merchant_request_id' => $stk['MerchantRequestID'] ?? null,
                    'mpesa_receipt' => $mpesaReceipt,
                    'phone' => $phone,
                    'amount' => $amount,
                    'status' => 'completed',
                    'metadata' => ['transaction_date' => $transactionDate, 'callback' => $stk],
                    'raw_response' => $this->callback,
                ]);
            }

            // Delegate activation to centralized finalizer
            try {
                $finalizer = app(\App\Http\Controllers\Api\SubscriptionPaymentController::class);
                $finalizer->finalizeFromCallback([
                    'checkout_request_id' => $checkoutId,
                    'mpesa_receipt' => $mpesaReceipt,
                    'phone' => $phone,
                    'amount' => $amount,
                    'merchant_request_id' => $stk['MerchantRequestID'] ?? null,
                    'transaction_date' => $transactionDate,
                    'raw_callback' => $this->callback,
                ]);
            } catch (\Throwable $_) {
                // ignore activation errors
            }
        }
    }
}

