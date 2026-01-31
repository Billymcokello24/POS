<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\MpesaService;
use App\Models\MpesaPayment;
use App\Http\Requests\StkPushRequest;
use App\Jobs\ProcessMpesaCallback;

class MpesaController extends Controller
{
    /**
     * Initiate STK Push
     */
    public function initiateMpesaPayment(StkPushRequest $request)
    {
        $business = auth()->user()->currentBusiness;
        if (!$business) {
            return response()->json(['success' => false, 'message' => 'No business found'], 403);
        }

        $mpesaService = new MpesaService($business);
        $result = $mpesaService->stkPush(
            $request->phone,
            $request->amount,
            $request->sale_id ?? null,
            $request->reference ?? null,
            'Payment'
        );

        $response = $result['response'];
        $payload = $result['payload'];
        $data = $response->successful() ? $response->json() : ['success' => false, 'body' => $response->body()];

        MpesaPayment::create([
            'business_id' => $business->id,
            'sale_id' => $request->sale_id ?? null,
            'checkout_request_id' => $data['CheckoutRequestID'] ?? null,
            'merchant_request_id' => $data['MerchantRequestID'] ?? null,
            'phone' => $request->phone,
            'amount' => $request->amount,
            'reference' => $request->reference ?? null,
            'account_reference' => $payload['AccountReference'] ?? null,
            'status' => $data['ResponseCode'] ?? ($response->successful() ? 'pending' : 'failed'),
            'raw_response' => $data,
            'metadata' => array_merge($payload, ['type' => $request->type ?? 'payment']),
        ]);

        return response()->json($data, $response->successful() ? 200 : 400);
    }

    /**
     * Check M-Pesa Payment Status (Poll Backend - Point 5)
     */
    public function checkMpesaStatus(Request $request)
    {
        $request->validate([
            'checkout_request_id' => 'required|string',
        ]);

        $payment = MpesaPayment::where('checkout_request_id', $request->checkout_request_id)->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment record not found',
            ], 404);
        }

        // Point 5: Active Polling Fallback
    // If status is still pending, ask M-Pesa directly (handles missed callbacks/localhost)
    if ($payment->status === 'pending') {
        try {
            // Blueprint Point 5: Always use the business associated with the payment
            $business = $payment->business;
            
            if ($business) {
                $mpesaService = new MpesaService($business);
                $response = $mpesaService->queryStkStatus($payment->checkout_request_id);

                // If we get a definitive ResultCode, update our ledger
                if (isset($response['ResultCode'])) {
                    $payment->update([
                        'result_code' => $response['ResultCode'],
                        'status' => MpesaPayment::resolveStatusFromCode($response['ResultCode']),
                        'raw_response' => array_merge($payment->raw_response ?? [], ['query_resp' => $response]),
                        'metadata' => array_merge($payment->metadata ?? [], ['source_update' => 'active_poll'])
                    ]);
                    
                    $payment->refresh();
                    Log::info('CheckStatus: Ledger updated via active poll', ['checkout' => $payment->checkout_request_id, 'code' => $payment->result_code]);
                }
            } else {
                Log::warning('CheckStatus: Payment found but no business linked', ['payment_id' => $payment->id]);
            }
        } catch (\Throwable $e) {
            Log::info('Active Polling: Query failed or transaction pending', ['msg' => $e->getMessage()]);
        }
    }
        
        // IMMEDIATE ACTIVATION ENFORCEMENT
        // If payment is successful (from callback or poll) but subscription not linked, force it now.
        if (($payment->status === 'success' || (string)$payment->result_code === '0') && !$payment->subscription_id) {
            try {
                // Force sync activation
                app(\App\Services\SubscriptionActivationService::class)->finalizeFromPayment([
                    'checkout_request_id' => $payment->checkout_request_id,
                    'mpesa_receipt' => $payment->receipt,
                    'result_code' => $payment->result_code
                ]);
                $payment->refresh();
            } catch (\Throwable $e) {
                Log::error('CheckStatus Force Activation Error', ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'CheckoutRequestID' => $payment->checkout_request_id,
                'ResultCode' => $payment->result_code !== null ? (string)$payment->result_code : null,
                'MpesaReceiptNumber' => $payment->receipt,
                'Status' => $payment->status,
                'subscription_id' => $payment->subscription_id,
                'ResultDesc' => $payment->metadata['result_desc'] ?? ($payment->status === 'success' ? 'Payment processed successfully' : 'Payment pending or failed'),
            ]
        ]);
    }

    /**
     * STK Callback
     */
    public function stkCallback(Request $request)
    {
        Log::info('MPESA STK CALLBACK', $request->all());

        // Process synchronously so activation happens immediately even if queue worker isn't running
        ProcessMpesaCallback::dispatchSync($request->all());

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    /**
     * Record Manual Till Payment (Consolidated Point 14)
     */
    public function recordTillPayment(Request $request)
    {
        $validated = $request->validate([
            'transaction_code' => 'required|string',
            'phone_number' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'sale_id' => 'nullable|exists:sales,id',
        ]);

        $business = auth()->user()->currentBusiness;
        $paymentService = app(\App\Services\PaymentService::class);
        $result = $paymentService->recordTillPayment($validated);

        if ($result['success'] && isset($validated['sale_id'])) {
            // Create payment record
            \App\Models\Payment::create([
                'sale_id' => $validated['sale_id'],
                'payment_method' => 'MPESA',
                'amount' => $validated['amount'],
                'status' => 'completed',
                'reference_number' => $validated['transaction_code'],
                'metadata' => [
                    'type' => 'till_payment',
                    'phone_number' => $result['phone_number'],
                    'transaction_code' => $validated['transaction_code'],
                ],
            ]);

            // Update sale status if fully paid
            $this->updateSaleStatus($validated['sale_id']);
        }

        return response()->json($result);
    }

    /**
     * Update sale status based on payments (Consolidated Point 14)
     */
    private function updateSaleStatus($saleId)
    {
        $sale = \App\Models\Sale::find($saleId);
        if (!$sale) return;

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

    /**
     * Till / Paybill Validation
     */
    public function validation(Request $request)
    {
        Log::info('MPESA VALIDATION', $request->all());

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted'
        ]);
    }

    /**
     * Till / Paybill Confirmation
     */
    public function confirmation(Request $request)
    {
        Log::info('MPESA CONFIRMATION', $request->all());

        MpesaPayment::create([
            'receipt' => $request->TransID,
            'phone' => $request->MSISDN,
            'amount' => $request->TransAmount,
            'reference' => $request->BillRefNumber,
            'status' => 'success',
            'raw_response' => $request->all()
        ]);

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Confirmed'
        ]);
    }
}
