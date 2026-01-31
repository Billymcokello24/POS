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
        if (!$stk) {
            Log::warning('ProcessMpesaCallback: Invalid callback structure', ['callback' => $callbackData]);
            return;
        }

        $checkoutRequestId = $stk['CheckoutRequestID'] ?? null;
        $resultCode = $stk['ResultCode'] ?? null;
        $resultDesc = $stk['ResultDesc'] ?? null;
        $merchantRequestId = $stk['MerchantRequestID'] ?? null;

        // 1. Update/Create MpesaPayment as the canonical ledger entry
        $mpesaPayment = \App\Models\MpesaPayment::where('checkout_request_id', $checkoutRequestId)->first();
        
        $status = \App\Models\MpesaPayment::resolveStatusFromCode($resultCode);
        
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

        $paymentData = [
            'result_code' => (int)$resultCode,
            'status' => $status,
            'receipt' => $mpesaReceiptNumber,
            'phone' => $phoneNumber ?? ($mpesaPayment->phone ?? null),
            'amount' => $amount ?? ($mpesaPayment->amount ?? null),
            'merchant_request_id' => $merchantRequestId,
            'raw_response' => $callbackData,
            'metadata' => array_merge($mpesaPayment->metadata ?? [], [
                'result_desc' => $resultDesc,
                'finalized_at' => now()->toDateTimeString(),
                'source' => 'mpesa_callback'
            ])
        ];

        if ($mpesaPayment) {
            $mpesaPayment->update($paymentData);
            Log::info('ProcessMpesaCallback: Updated MpesaPayment ledger', ['checkout' => $checkoutRequestId, 'status' => $status]);
        } else {
            // Unexpected callback without initiation record, but we should record it anyway
            $mpesaPayment = \App\Models\MpesaPayment::create(array_merge($paymentData, [
                'checkout_request_id' => $checkoutRequestId,
                'business_id' => null, // Will try to resolve if needed
            ]));
            Log::warning('ProcessMpesaCallback: Created MpesaPayment for orphaned callback', ['checkout' => $checkoutRequestId]);
        }

        // 2. Resolve Activation (Subscriptions & Sales)
        if ($mpesaPayment->result_code === 0) {
            Log::info('ProcessMpesaCallback: Successful payment recorded. Triggering finalizers.', ['checkout' => $checkoutRequestId]);
            
            // 2a. Subscription Finalization
            try {
                // Point 1 & 6: Immediate activation enforcement
                app(\App\Services\SubscriptionActivationService::class)->finalizeFromPayment([
                    'checkout_request_id' => $checkoutRequestId,
                    'mpesa_receipt' => $mpesaReceiptNumber,
                    'result_code' => $resultCode,
                    'phone' => $phoneNumber,
                    'amount' => $amount,
                ]);
            } catch (\Throwable $e) {
                Log::error('ProcessMpesaCallback: Subscription finalization failed', ['error' => $e->getMessage()]);
            }

            // 2b. Legacy Sales Finalization (Point 14: Unified Handler)
            try {
                $payment = \App\Models\Payment::where('reference_number', $checkoutRequestId)
                    ->where('status', 'pending')
                    ->first();

                if ($payment) {
                    $payment->update([
                        'status' => 'completed',
                        'reference_number' => $mpesaReceiptNumber,
                        'metadata' => array_merge($payment->metadata ?? [], [
                            'mpesa_receipt' => $mpesaReceiptNumber,
                            'finalized_at' => now()->toDateTimeString(),
                            'source' => 'universal_callback_handler'
                        ])
                    ]);

                    // Update Sale status to completed if fully paid (Robust Point 14)
                    if ($payment->sale) {
                        $sale = $payment->sale;
                        $totalPaid = \App\Models\Payment::where('sale_id', $sale->id)
                            ->where('status', 'completed')
                            ->sum('amount');
                        
                        if ($totalPaid >= $sale->total) {
                            $sale->update([
                                'status' => 'completed',
                                'completed_at' => now(),
                            ]);
                            Log::info('ProcessMpesaCallback: Sale fully paid and completed', ['sale_id' => $sale->id]);
                        }
                    }
                    Log::info('ProcessMpesaCallback: Legacy Sales payment finalized', ['payment_id' => $payment->id]);
                }
            } catch (\Throwable $e) {
                Log::error('ProcessMpesaCallback: Sales finalization failed', ['error' => $e->getMessage()]);
            }
            
            $mpesaPayment->refresh();
            Log::info('ProcessMpesaCallback: Finalization process complete', ['mpesa_id' => $mpesaPayment->id, 'status' => $mpesaPayment->status]);

        } else {
            Log::warning('ProcessMpesaCallback: Payment failed or non-zero result recorded.', ['checkout' => $checkoutRequestId, 'result_code' => $resultCode]);
            
            // Update subscription to failed if applicable
            try {
                app(\App\Services\SubscriptionActivationService::class)->finalizeFromPayment([
                    'checkout_request_id' => $checkoutRequestId,
                    'result_code' => $resultCode
                ]);
            } catch (\Throwable $_) {}

            // Update legacy sales payment to failed
            try {
                $payment = \App\Models\Payment::where('reference_number', $checkoutRequestId)
                    ->where('status', 'pending')
                    ->first();
                if ($payment) {
                    $payment->update(['status' => 'failed']);
                }
            } catch (\Throwable $_) {}
        }
    }
}

