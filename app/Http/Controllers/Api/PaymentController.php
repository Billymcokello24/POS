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

        $result = $this->paymentService->initiateMpesaStkPush($stkData);

        if ($result['success']) {
            // Store pending payment record
            if (isset($validated['sale_id'])) {
                Payment::create([
                    'sale_id' => $validated['sale_id'],
                    'payment_method' => 'MPESA',
                    'amount' => $validated['amount'],
                    'status' => 'pending',
                    'reference_number' => $result['checkout_request_id'],
                    'metadata' => $result,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $result['customer_message'] ?? 'Payment request sent. Please check your phone.',
                'data' => [
                    'checkout_request_id' => $result['checkout_request_id'],
                    'merchant_request_id' => $result['merchant_request_id'],
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

        $result = $this->paymentService->queryStkPushStatus($validated['checkout_request_id']);

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
     * M-Pesa Callback Handler
     */
    public function mpesaCallback(Request $request)
    {
        Log::info('M-Pesa Callback Received', $request->all());

        $callbackData = $request->all();

        // Extract callback metadata
        $resultCode = $callbackData['Body']['stkCallback']['ResultCode'] ?? null;
        $resultDesc = $callbackData['Body']['stkCallback']['ResultDesc'] ?? null;
        $checkoutRequestID = $callbackData['Body']['stkCallback']['CheckoutRequestID'] ?? null;

        if ($resultCode === 0) {
            // Payment successful
            $metadata = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'] ?? [];

            $mpesaReceiptNumber = null;
            $amount = null;
            $phoneNumber = null;

            foreach ($metadata as $item) {
                if ($item['Name'] === 'MpesaReceiptNumber') {
                    $mpesaReceiptNumber = $item['Value'];
                }
                if ($item['Name'] === 'Amount') {
                    $amount = $item['Value'];
                }
                if ($item['Name'] === 'PhoneNumber') {
                    $phoneNumber = $item['Value'];
                }
            }

            // Find and update payment
            $payment = Payment::where('reference_number', $checkoutRequestID)
                ->where('status', 'pending')
                ->first();

            if ($payment) {
                $payment->update([
                    'status' => 'completed',
                    'reference_number' => $mpesaReceiptNumber,
                    'metadata' => [
                        'mpesa_receipt' => $mpesaReceiptNumber,
                        'amount' => $amount,
                        'phone_number' => $phoneNumber,
                        'callback_data' => $callbackData,
                    ],
                ]);

                $this->updateSaleStatus($payment->sale_id);

                Log::info('M-Pesa Payment Completed', [
                    'payment_id' => $payment->id,
                    'receipt' => $mpesaReceiptNumber
                ]);
            }
        } else {
            // Payment failed
            $payment = Payment::where('reference_number', $checkoutRequestID)
                ->where('status', 'pending')
                ->first();

            if ($payment) {
                $payment->update([
                    'status' => 'failed',
                    'metadata' => [
                        'result_code' => $resultCode,
                        'result_desc' => $resultDesc,
                        'callback_data' => $callbackData,
                    ],
                ]);

                Log::warning('M-Pesa Payment Failed', [
                    'payment_id' => $payment->id,
                    'reason' => $resultDesc
                ]);
            }
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

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

