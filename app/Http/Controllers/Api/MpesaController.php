<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaController extends Controller
{
    /**
     * Handle STK Push Callback (Unauthenticated Safaricom Endpoint)
     */
    public function callback(Request $request)
    {
        $callbackData = $request->all();
        Log::info('M-Pesa Callback Received', $callbackData);

        if (! isset($callbackData['Body']['stkCallback'])) {
            return response()->json(['status' => 'error', 'message' => 'Invalid callback data']);
        }

        $stkCallback = $callbackData['Body']['stkCallback'];
        $resultCode = $stkCallback['ResultCode'];
        $checkoutRequestId = $stkCallback['CheckoutRequestID'];

        // 1. Check if it's a Subscription Payment
        $subscription = Subscription::where('transaction_id', $checkoutRequestId)
            ->where('status', 'pending')
            ->first();

        if ($subscription) {
            return $this->handleSubscriptionCallback($subscription, $resultCode, $stkCallback);
        }

        // 2. Check if it's a Sale Payment (Existing POS logic)
        $payment = \App\Models\Payment::where('reference_number', $checkoutRequestId)
            ->where('status', 'pending')
            ->first();

        if ($payment) {
            return $this->handleSaleCallback($payment, $resultCode, $stkCallback);
        }

        Log::warning('M-Pesa Callback: Transaction not found in system', ['CheckoutRequestID' => $checkoutRequestId]);

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    protected function handleSubscriptionCallback($subscription, $resultCode, $stkCallback)
    {
        $checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? null;

        if ($resultCode == 0) {
            // Parse metadata
            $callbackMetadata = $stkCallback['CallbackMetadata']['Item'] ?? [];
            $mpesaReceiptNumber = $this->getMetadataValue($callbackMetadata, 'MpesaReceiptNumber');
            $amount = $this->getMetadataValue($callbackMetadata, 'Amount');
            $phone = $this->getMetadataValue($callbackMetadata, 'PhoneNumber');
            $transactionDate = $this->getMetadataValue($callbackMetadata, 'TransactionDate');

            // Update or create MpesaPayment record
            try {
                $mp = \App\Models\MpesaPayment::where('checkout_request_id', $checkoutRequestId)->first();
                if ($mp) {
                    $mp->update([
                        'merchant_request_id' => $stkCallback['MerchantRequestID'] ?? $mp->merchant_request_id,
                        'receipt' => $mpesaReceiptNumber ?? $mp->receipt,
                        'phone' => $phone ?? $mp->phone,
                        'amount' => $amount ?? $mp->amount,
                        'status' => 'success',
                        'raw_response' => $stkCallback,
                        'metadata' => array_merge($mp->metadata ?? [], ['transaction_date' => $transactionDate]),
                    ]);
                } else {
                    \App\Models\MpesaPayment::create([
                        'business_id' => $subscription->business_id,
                        'sale_id' => null,
                        'checkout_request_id' => $checkoutRequestId,
                        'merchant_request_id' => $stkCallback['MerchantRequestID'] ?? null,
                        'receipt' => $mpesaReceiptNumber ?? null,
                        'phone' => $phone ?? null,
                        'amount' => $amount ?? null,
                        'account_reference' => $subscription->id ? ('SUB-'.$subscription->id) : null,
                        'status' => 'success',
                        'raw_response' => $stkCallback,
                        'metadata' => ['transaction_date' => $transactionDate],
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Failed to upsert MpesaPayment in subscription callback: '.$e->getMessage());
            }

            // Create or update a SubscriptionPayment entry for this subscription
            $paymentRecorded = false;
            try {
                $sp = \App\Models\SubscriptionPayment::where('checkout_request_id', $checkoutRequestId)
                    ->orWhere('mpesa_receipt', $mpesaReceiptNumber)
                    ->first();

                if ($sp) {
                    $sp->update([
                        'mpesa_receipt' => $mpesaReceiptNumber ?? $sp->mpesa_receipt,
                        'phone' => $phone ?? $sp->phone,
                        'amount' => $amount ?? $sp->amount,
                        'status' => 'completed',
                        'raw_response' => $stkCallback,
                        'metadata' => array_merge($sp->metadata ?? [], ['transaction_date' => $transactionDate, 'callback' => $stkCallback]),
                    ]);
                    $paymentRecorded = true;
                } else {
                    $newSp = \App\Models\SubscriptionPayment::create([
                        'subscription_id' => $subscription->id ?? null,
                        'business_id' => $subscription->business_id ?? null,
                        'checkout_request_id' => $checkoutRequestId,
                        'merchant_request_id' => $stkCallback['MerchantRequestID'] ?? null,
                        'mpesa_receipt' => $mpesaReceiptNumber ?? null,
                        'phone' => $phone ?? null,
                        'amount' => $amount ?? null,
                        'status' => 'completed',
                        'metadata' => ['transaction_date' => $transactionDate, 'callback' => $stkCallback],
                        'raw_response' => $stkCallback,
                    ]);

                    $paymentRecorded = (bool) $newSp;
                }
            } catch (\Throwable $e) {
                Log::error('Failed to upsert SubscriptionPayment in subscription callback: '.$e->getMessage());
            }

            // Only set subscription to pending_verification if subscription_payment recorded
            if ($paymentRecorded) {
                try {
                    $subscription->update([
                        'transaction_id' => $mpesaReceiptNumber ?? $checkoutRequestId,
                        'status' => 'pending_verification',
                        'payment_details' => array_merge($subscription->payment_details ?? [], [
                            'mpesa_receipt' => $mpesaReceiptNumber ?? null,
                            'phone' => $phone ?? null,
                            'amount' => $amount ?? null,
                            'transaction_date' => $transactionDate ?? null,
                            'raw_callback' => $stkCallback,
                        ]),
                    ]);

                    Log::info('Subscription marked pending_verification (API callback)', ['subscription_id' => $subscription->id, 'checkout' => $checkoutRequestId]);
                } catch (\Throwable $e) {
                    Log::error('Failed to mark subscription pending_verification (API callback): '.$e->getMessage());
                }
            }

        } else {
            // failure flow
            try {
                $subscription->update([
                    'status' => 'failed',
                    'payment_details' => array_merge($subscription->payment_details ?? [], ['failure_reason' => $stkCallback['ResultDesc'] ?? null]),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to mark subscription failed in API callback: '.$e->getMessage());
            }
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    protected function handleSaleCallback($payment, $resultCode, $stkCallback)
    {
        if ($resultCode == 0) {
            $callbackMetadata = $stkCallback['CallbackMetadata']['Item'];
            $mpesaReceiptNumber = $this->getMetadataValue($callbackMetadata, 'MpesaReceiptNumber');
            $amount = $this->getMetadataValue($callbackMetadata, 'Amount');

            $payment->update([
                'status' => 'completed',
                'reference_number' => $mpesaReceiptNumber,
                'metadata' => array_merge($payment->metadata ?? [], [
                    'mpesa_receipt' => $mpesaReceiptNumber,
                    'amount_confirmed' => $amount,
                    'callback_data' => $stkCallback,
                ]),
            ]);

            // Update Sale status
            $this->updateSaleStatus($payment->sale_id);

            Log::info('M-Pesa Sale Payment Successful', ['payment_id' => $payment->id]);
        } else {
            $payment->update([
                'status' => 'failed',
                'metadata' => array_merge($payment->metadata ?? [], [
                    'failure_reason' => $stkCallback['ResultDesc'],
                ]),
            ]);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    protected function getMetadataValue($metadata, $name)
    {
        foreach ($metadata as $item) {
            if ($item['Name'] === $name) {
                return $item['Value'] ?? null;
            }
        }

        return null;
    }

    protected function activateSubscription($subscription, $receipt, $callbackData)
    {
        $subscription->business->activateSubscription($subscription, $receipt, [
            'stk_callback' => $callbackData,
        ]);

        Log::info("Subscription activated via M-Pesa for Business #{$subscription->business_id}");
    }

    private function updateSaleStatus($saleId)
    {
        $sale = \App\Models\Sale::find($saleId);
        if (! $sale) {
            return;
        }

        $totalPaid = \App\Models\Payment::where('sale_id', $saleId)
            ->where('status', 'completed')
            ->sum('amount');

        if ($totalPaid >= $sale->total) {
            $sale->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }
    }
}
