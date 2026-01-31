<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Initiate M-Pesa STK Push
     */
    public function initiateMpesaPayment(Request $request)
    {
        $validated = $request->validate([
            'phone_number' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'sale_id' => 'nullable|exists:sales,id',
            'account_reference' => 'nullable|string',
        ]);

        // Validate phone number
        $phoneValidation = $this->paymentService->validatePhoneNumber($validated['phone_number']);

        if (!$phoneValidation['valid']) {
            return response()->json([
                'success' => false,
                'message' => $phoneValidation['message']
            ], 400);
        }

        // Prepare data for STK Push
        $stkData = [
            'phone_number' => $phoneValidation['formatted'],
            'amount' => $validated['amount'],
            'account_reference' => $validated['account_reference'] ?? 'POS-SALE-' . ($validated['sale_id'] ?? uniqid()),
            'transaction_desc' => 'Payment for goods',
        ];

        // Attach per-business mpesa credentials if available
        $business = auth()->user()->currentBusiness;
        if ($business && isset($business->settings['mpesa'])) {
            $stkData['mpesa'] = $business->settings['mpesa'];
        }

        // Validate callback URL (must be publicly reachable). Safaricom rejects localhost.
        $mpesaSettings = $stkData['mpesa'] ?? null;
        $callbackToCheck = $mpesaSettings['callback_url'] ?? config('mpesa.callback_url');
        if ($callbackToCheck) {
            $parsed = parse_url($callbackToCheck);
            $host = $parsed['host'] ?? null;
            $scheme = $parsed['scheme'] ?? null;

            // Reject common local hosts that Safaricom cannot reach
            if (in_array($host, ['127.0.0.1', 'localhost']) || str_starts_with($host ?? '', '127.')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid M-Pesa callback URL configured for this business. The callback must be publicly accessible (not localhost). Use an ngrok or public HTTPS URL and update Business Settings.'
                ], 400);
            }

            // If environment is production enforce HTTPS
            $env = $mpesaSettings['environment'] ?? config('mpesa.environment', 'production');
            if ($env === 'production' && strtolower($scheme) !== 'https') {
                return response()->json([
                    'success' => false,
                    'message' => 'MPESA callback URL must use HTTPS in production. Please update the business MPESA callback URL to an HTTPS endpoint.'
                ], 400);
            }
        }

        $result = $this->paymentService->initiateMpesaStkPush($stkData);

        if ($result['success']) {
            // Store pending payment record with the mapping used so we can query status with same BusinessShortCode
            if (isset($validated['sale_id'])) {
                $metadata = $result;
                // ensure metadata includes mapping info
                $metadata['used_mapping'] = $result['used_mapping'] ?? null;
                $metadata['used_business_shortcode'] = $result['used_business_shortcode'] ?? null;

                Payment::create([
                    'sale_id' => $validated['sale_id'],
                    'payment_method' => 'MPESA',
                    'amount' => $validated['amount'],
                    'status' => 'pending',
                    'reference_number' => $result['checkout_request_id'],
                    'metadata' => $metadata,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $result['customer_message'] ?? 'Payment request sent. Please check your phone.',
                'data' => [
                    'checkout_request_id' => $result['checkout_request_id'],
                    'merchant_request_id' => $result['merchant_request_id'],
                    'used_mapping' => $result['used_mapping'] ?? null,
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to initiate payment',
            'error' => $result['error'] ?? null
        ], 400);
    }

    /**
     * Check M-Pesa payment status
     */
    public function checkMpesaStatus(Request $request)
    {
        $validated = $request->validate([
            'checkout_request_id' => 'required|string',
        ]);

        $checkout = $validated['checkout_request_id'];

        // 1. Authoritative check against MpesaPayment Ledger (Canonical Truth)
        try {
            $mp = \App\Models\MpesaPayment::where('checkout_request_id', $checkout)
                ->orWhere('receipt', $checkout)
                ->latest()
                ->first();

            if ($mp && $mp->status === \App\Models\MpesaPayment::STATUS_SUCCESS) {
                // If it's successful, ensure activation service is triggered (idempotent)
                $finalizer = app(\App\Http\Controllers\Api\SubscriptionPaymentController::class);
                $finalizer->finalizeFromCallback([
                    'checkout_request_id' => $mp->checkout_request_id,
                    'mpesa_receipt' => $mp->receipt,
                    'result_code' => $mp->result_code,
                    'phone' => $mp->phone,
                    'amount' => $mp->amount,
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'ResultCode' => '0',
                        'MpesaReceiptNumber' => $mp->receipt,
                        'ResultDesc' => 'Payment successful',
                        'auto_activated' => true,
                    ]
                ]);
            }
        } catch (\Throwable $_) {}

        // 2. Fallback to SubscriptionPayment check
        try {
            $sp = \App\Models\SubscriptionPayment::where(function($q) use ($checkout) {
                $q->where('checkout_request_id', $checkout)
                  ->orWhere('mpesa_receipt', $checkout);
            })->whereIn('status', ['completed', 'success'])->latest()->first();

            if ($sp) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'ResultCode' => '0',
                        'MpesaReceiptNumber' => $sp->mpesa_receipt,
                        'ResultDesc' => 'Payment completed successfully',
                        'auto_activated' => true,
                    ]
                ]);
            }
        } catch (\Throwable $_) { }

        // Existing logic below remains unchanged
        // First check database for already processed payment
        try {
            $subscription = \App\Models\Subscription::where('checkout_request_id', $checkout)
                ->orWhere('transaction_id', $checkout)
                ->first();

            if ($subscription && $subscription->mpesa_receipt && $subscription->status === 'active') {
                // Already processed and activated
                return response()->json([
                    'success' => true,
                    'data' => [
                        'ResultCode' => '0',
                        'MpesaReceiptNumber' => $subscription->mpesa_receipt,
                        'ResultDesc' => 'Payment completed successfully',
                        'subscription_id' => $subscription->id,
                        'auto_activated' => true,
                    ]
                ]);
            }
        } catch (\Throwable $_) { }

        // Pass per-business credentials if available
        $credentials = null;
        $business = auth()->user()->currentBusiness;
        if ($business && isset($business->settings['mpesa'])) {
            $credentials = $business->settings['mpesa'];
        }

        // If there is a pending payment with mapping info, prefer that mapping for querying status
        $payment = \App\Models\Payment::where('reference_number', $checkout)->first();
        if ($payment && is_array($payment->metadata ?? [])) {
            $meta = $payment->metadata;
            if (!empty($meta['used_business_shortcode'])) {
                // override shortcode to the one used when initiating STK push
                $credentials = array_merge($credentials ?? [], ['shortcode' => $meta['used_business_shortcode']]);
            }
            if (!empty($meta['used_passkey'])) {
                // override passkey to the one used when initiating STK push
                $credentials = array_merge($credentials ?? [], ['passkey' => $meta['used_passkey']]);
            } elseif (!empty($meta['used_mapping']) && $meta['used_mapping'] === 'store_as_business' && !empty($meta['used_business_shortcode'])) {
                $credentials = array_merge($credentials ?? [], ['shortcode' => $meta['used_business_shortcode']]);
            }
        }

        $result = $this->paymentService->queryStkPushStatus($checkout, $credentials);

        // If M-Pesa reports success, try to auto-activate the subscription (backend is source of truth)
        try {
            $data = $result['data'] ?? [];
            $resultCode = isset($data['ResultCode']) ? (string)$data['ResultCode'] : null;
            $mpesaReceipt = $data['MpesaReceiptNumber'] ?? $data['MpesaReceipt'] ?? null;

            if ($result['success'] && $resultCode === '0' && $mpesaReceipt) {
                // Centralize activation via SubscriptionPaymentController
                $finalizer = app(\App\Http\Controllers\Api\SubscriptionPaymentController::class);
                $ok = $finalizer->finalizeFromCallback([
                    'checkout_request_id' => $checkout,
                    'mpesa_receipt' => $mpesaReceipt,
                    'phone' => $data['PhoneNumber'] ?? null,
                    'amount' => $data['Amount'] ?? null,
                    'merchant_request_id' => $data['MerchantRequestID'] ?? null,
                    'raw_callback' => $data,
                ]);

                if ($ok) {
                    $subscription = \App\Models\Subscription::where('transaction_id', $checkout)
                        ->orWhere('transaction_id', $mpesaReceipt)
                        ->orWhere('checkout_request_id', $checkout)
                        ->first();

                    if ($subscription) {
                        // augment result with subscription id and auto_activated flag for frontend convenience
                        $result['data']['subscription_id'] = $subscription->id;
                        $result['data']['auto_activated'] = true;
                        Log::info('checkMpesaStatus: subscription auto-activated via polling sync', ['subscription_id' => $subscription->id, 'receipt' => $mpesaReceipt]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('checkMpesaStatus: auto-activation failed', ['error' => $e->getMessage()]);
        }

        return response()->json($result);
    }

    /**
     * Record M-Pesa Till Payment
     */
    public function recordTillPayment(Request $request)
    {
        $validated = $request->validate([
            'transaction_code' => 'required|string',
            'phone_number' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'sale_id' => 'nullable|exists:sales,id',
        ]);

        $result = $this->paymentService->recordTillPayment($validated);

        if ($result['success'] && isset($validated['sale_id'])) {
            // Create payment record
            Payment::create([
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
     * Process Card Payment
     */
    public function processCardPayment(Request $request)
    {
        $validated = $request->validate([
            'card_number' => 'required|string|size:16',
            'expiry_month' => 'required|integer|min:1|max:12',
            'expiry_year' => 'required|integer|min:' . date('Y'),
            'cvv' => 'required|string|size:3',
            'cardholder_name' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'sale_id' => 'nullable|exists:sales,id',
        ]);

        $result = $this->paymentService->processCardPayment($validated);

        if ($result['success'] && isset($validated['sale_id'])) {
            // Mask card number for storage
            $maskedCard = '**** **** **** ' . substr($validated['card_number'], -4);

            Payment::create([
                'sale_id' => $validated['sale_id'],
                'payment_method' => 'CARD',
                'amount' => $validated['amount'],
                'status' => 'completed',
                'reference_number' => $result['transaction_id'],
                'metadata' => [
                    'card_number' => $maskedCard,
                    'cardholder' => $validated['cardholder_name'],
                    'transaction_id' => $result['transaction_id'],
                ],
            ]);

            $this->updateSaleStatus($validated['sale_id']);
        }

        return response()->json($result);
    }

    /**
     * Record Bank Transfer
     */
    public function recordBankTransfer(Request $request)
    {
        $validated = $request->validate([
            'reference_number' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'bank_name' => 'nullable|string',
            'account_number' => 'nullable|string',
            'sale_id' => 'nullable|exists:sales,id',
        ]);

        $result = $this->paymentService->recordBankTransfer($validated);

        if ($result['success'] && isset($validated['sale_id'])) {
            Payment::create([
                'sale_id' => $validated['sale_id'],
                'payment_method' => 'BANK_TRANSFER',
                'amount' => $validated['amount'],
                'status' => 'completed',
                'reference_number' => $validated['reference_number'],
                'metadata' => [
                    'bank_name' => $validated['bank_name'] ?? null,
                    'account_number' => $validated['account_number'] ?? null,
                ],
            ]);

            $this->updateSaleStatus($validated['sale_id']);
        }

        return response()->json($result);
    }

    /**
     * Record Cash Payment
     */
    public function recordCashPayment(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'received_amount' => 'required|numeric|min:0',
            'sale_id' => 'nullable|exists:sales,id',
        ]);

        $change = $validated['received_amount'] - $validated['amount'];

        if ($change < 0) {
            return response()->json([
                'success' => false,
                'message' => 'Received amount is less than the payment amount'
            ], 400);
        }

        if (isset($validated['sale_id'])) {
            Payment::create([
                'sale_id' => $validated['sale_id'],
                'payment_method' => 'CASH',
                'amount' => $validated['amount'],
                'status' => 'completed',
                'metadata' => [
                    'received_amount' => $validated['received_amount'],
                    'change' => $change,
                ],
            ]);

            $this->updateSaleStatus($validated['sale_id']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cash payment recorded successfully',
            'change' => $change,
        ]);
    }

    /**
     * M-Pesa Callback Handler (Deprecated - logic moved to MpesaController@stkCallback and ProcessMpesaCallback)
     */
    // public function mpesaCallback(Request $request) { ... }

    /**
     * Update sale status based on payments
     */
    private function updateSaleStatus($saleId)
    {
        $sale = Sale::find($saleId);

        if (!$sale) {
            return;
        }

        $totalPaid = Payment::where('sale_id', $saleId)
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
