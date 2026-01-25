<?php

namespace App\Jobs;

use App\Models\MpesaPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        $stk = $this->callback['Body']['stkCallback'] ?? null;
        if (!$stk) return;

        $checkoutId = $stk['CheckoutRequestID'] ?? null;
        $resultCode = $stk['ResultCode'] ?? null;

        // Try to find the payment by checkout id
        $payment = $checkoutId ? MpesaPayment::where('checkout_request_id', $checkoutId)->first() : null;

        // Parse metadata/AccountReference if provided to resolve business
        $accountRef = null;
        if (isset($stk['CallbackMetadata']['Item'])) {
            $items = collect($stk['CallbackMetadata']['Item'])->pluck('Value', 'Name');
            $accountRef = $items['AccountReference'] ?? ($items['Reference'] ?? null);
        }

        // If payment not found by checkout id, try account_reference field pattern BIZ-{id}-...
        if (!$payment && $accountRef) {
            if (preg_match('/^BIZ-(\d+)-/', $accountRef, $m)) {
                $businessId = (int) $m[1];
                $payment = MpesaPayment::where('business_id', $businessId)
                    ->where('account_reference', $accountRef)
                    ->latest()
                    ->first();
            }
        }

        // Check if this is a subscription payment (independent from sales)
        $subscription = null;
        if ($checkoutId) {
            $subscription = \App\Models\Subscription::where('transaction_id', $checkoutId)->where('status', 'pending')->first();
        }

        // If still not found and checkout id exists, create a record
        if (!$payment && $checkoutId) {
            $payment = MpesaPayment::create([
                'business_id' => $this->callback['BusinessID'] ?? null,
                'sale_id' => null,
                'checkout_request_id' => $checkoutId,
                'merchant_request_id' => $stk['MerchantRequestID'] ?? null,
                'phone' => null,
                'amount' => null,
                'reference' => null,
                'account_reference' => $accountRef,
                'status' => $resultCode === 0 ? 'success' : 'failed',
                'raw_response' => $this->callback,
            ]);
        }

        if ($resultCode !== 0) {
            if ($payment) {
                $payment->update(['status' => 'failed', 'raw_response' => $this->callback]);
            }
            // Also fail subscription if found
            if ($subscription) {
                $subscription->update(['status' => 'failed', 'payment_details' => array_merge($subscription->payment_details ?? [], ['failure_reason' => $stk['ResultDesc'] ?? null])]);
            }
            return;
        }

        // Extract callback metadata items
        $items = collect($stk['CallbackMetadata']['Item'])->pluck('Value', 'Name');

        if ($payment) {
            $payment->update([
                'receipt' => $items['MpesaReceiptNumber'] ?? null,
                'phone' => $items['PhoneNumber'] ?? null,
                'amount' => $items['Amount'] ?? null,
                'status' => 'success',
                'raw_response' => $this->callback,
            ]);
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
                \App\Models\SubscriptionPayment::create([
                    'subscription_id' => $subscription->id,
                    'business_id' => $subscription->business_id,
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

            // Update subscription
            $subscription->update([
                'transaction_id' => $mpesaReceipt ?? $checkoutId,
                'status' => 'pending_verification',
                'payment_details' => array_merge($subscription->payment_details ?? [], [
                    'mpesa_receipt' => $mpesaReceipt,
                    'phone' => $phone,
                    'amount' => $amount,
                    'transaction_date' => $transactionDate,
                    'raw_callback' => $stk,
                ]),
            ]);
        }
    }
}
