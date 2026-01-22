<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /**
     * Initiate M-Pesa STK Push
     */
    public function initiateMpesaStkPush(array $data)
    {
        $phoneNumber = $this->formatPhoneNumber($data['phone_number']);
        $amount = (int) $data['amount'];
        $accountReference = $data['account_reference'] ?? 'POS-SALE';
        $transactionDesc = $data['transaction_desc'] ?? 'Payment for goods';

        // Get M-Pesa credentials from config
        $consumerKey = config('mpesa.consumer_key');
        $consumerSecret = config('mpesa.consumer_secret');
        $shortcode = config('mpesa.shortcode');
        $headOfficeShortcode = config('mpesa.head_office_shortcode');
        $passkey = config('mpesa.passkey');
        $callbackUrl = config('mpesa.callback_url');
        $environment = config('mpesa.environment', 'production');

        // Validate credentials
        if (empty($consumerKey) || empty($consumerSecret) || empty($shortcode) || empty($passkey)) {
            return [
                'success' => false,
                'message' => 'M-Pesa credentials not configured. Please check your .env file.',
                'error' => 'Missing credentials'
            ];
        }

        // Get URLs based on environment
        $urls = config("mpesa.urls.{$environment}");

        try {
            // Step 1: Get Access Token with 30 second timeout and retry
            $authResponse = Http::timeout(30)
                ->retry(2, 100) // Retry twice with 100ms delay
                ->withBasicAuth($consumerKey, $consumerSecret)
                ->get($urls['oauth'] . '?grant_type=client_credentials');

            if (!$authResponse->successful()) {
                Log::error('M-Pesa Auth Failed', ['response' => $authResponse->json()]);
                return [
                    'success' => false,
                    'message' => 'Failed to authenticate with M-Pesa',
                    'error' => $authResponse->json()
                ];
            }

            $accessToken = $authResponse->json('access_token');

            // Step 2: Generate Password and Timestamp
            $timestamp = now()->format('YmdHis');

            // Determine transaction type based on shortcode length
            // Till numbers are typically 6-7 digits, PayBill are 5-6 digits
            $isTillNumber = strlen($shortcode) >= 6;
            $transactionType = $isTillNumber ? 'CustomerBuyGoodsOnline' : 'CustomerPayBillOnline';

            // For STK Push, BusinessShortCode is ALWAYS the shortcode itself
            // This applies to both Till Numbers and PayBill
            $businessShortCode = $shortcode;
            $partyB = $shortcode;

            // Password is generated with the shortcode
            $password = base64_encode($businessShortCode . $passkey . $timestamp);

            // Log STK Push request details
            Log::info('Initiating M-Pesa STK Push', [
                'phone' => $phoneNumber,
                'amount' => $amount,
                'shortcode' => $shortcode,
                'business_shortcode' => $businessShortCode,
                'party_b' => $partyB,
                'transaction_type' => $transactionType,
                'is_till_number' => $isTillNumber,
                'callback_url' => $callbackUrl,
                'account_ref' => $accountReference,
                'timestamp' => $timestamp
            ]);

            // Step 3: Initiate STK Push with 30 second timeout and retry
            $stkResponse = Http::timeout(30)
                ->retry(2, 100) // Retry twice with 100ms delay
                ->withToken($accessToken)
                ->post($urls['stk_push'], [
                    'BusinessShortCode' => $businessShortCode,
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'TransactionType' => $transactionType,
                    'Amount' => $amount,
                    'PartyA' => $phoneNumber,
                    'PartyB' => $partyB,
                    'PhoneNumber' => $phoneNumber,
                    'CallBackURL' => $callbackUrl,
                    'AccountReference' => $accountReference,
                    'TransactionDesc' => $transactionDesc,
                ]);

            if (!$stkResponse->successful()) {
                Log::error('M-Pesa STK Push Failed', ['response' => $stkResponse->json()]);
                return [
                    'success' => false,
                    'message' => 'Failed to initiate M-Pesa payment',
                    'error' => $stkResponse->json()
                ];
            }

            $responseData = $stkResponse->json();

            // Check response code
            if ($responseData['ResponseCode'] === '0') {
                return [
                    'success' => true,
                    'message' => 'STK Push sent successfully',
                    'checkout_request_id' => $responseData['CheckoutRequestID'],
                    'merchant_request_id' => $responseData['MerchantRequestID'],
                    'response_code' => $responseData['ResponseCode'],
                    'response_description' => $responseData['ResponseDescription'],
                    'customer_message' => $responseData['CustomerMessage'] ?? 'Please check your phone to complete payment',
                ];
            }

            return [
                'success' => false,
                'message' => $responseData['ResponseDescription'] ?? 'STK Push failed',
                'error' => $responseData
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('M-Pesa Connection Timeout', [
                'message' => $e->getMessage(),
                'phone' => $phoneNumber,
                'amount' => $amount
            ]);

            return [
                'success' => false,
                'message' => 'Connection to M-Pesa timed out. Please check your internet connection and try again.',
                'error' => 'Connection timeout'
            ];
        } catch (\Exception $e) {
            Log::error('M-Pesa STK Push Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while processing M-Pesa payment',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Query STK Push transaction status
     */
    public function queryStkPushStatus($checkoutRequestId)
    {
        $consumerKey = config('mpesa.consumer_key');
        $consumerSecret = config('mpesa.consumer_secret');
        $shortcode = config('mpesa.shortcode');
        $passkey = config('mpesa.passkey');
        $environment = config('mpesa.environment', 'production');

        $urls = config("mpesa.urls.{$environment}");

        try {
            // Get Access Token with 10 second timeout
            $authResponse = Http::timeout(10)
                ->withBasicAuth($consumerKey, $consumerSecret)
                ->get($urls['oauth'] . '?grant_type=client_credentials');

            if (!$authResponse->successful()) {
                Log::warning('M-Pesa Auth Failed for Query', [
                    'status' => $authResponse->status(),
                    'body' => $authResponse->body()
                ]);
                return ['success' => false, 'message' => 'Authentication failed'];
            }

            $accessToken = $authResponse->json('access_token');
            $timestamp = now()->format('YmdHis');
            $password = base64_encode($shortcode . $passkey . $timestamp);

            // Query STK Push status with 10 second timeout
            $queryResponse = Http::timeout(10)
                ->withToken($accessToken)
                ->post($urls['stk_query'], [
                    'BusinessShortCode' => $shortcode,
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'CheckoutRequestID' => $checkoutRequestId,
                ]);

            if (!$queryResponse->successful()) {
                Log::warning('M-Pesa Query Failed', [
                    'status' => $queryResponse->status(),
                    'body' => $queryResponse->body(),
                    'checkout_id' => $checkoutRequestId
                ]);

                // Return pending status instead of error for network issues
                return [
                    'success' => true,
                    'data' => [
                        'ResultCode' => '1037', // Pending
                        'ResultDesc' => 'Request processing'
                    ]
                ];
            }

            $responseData = $queryResponse->json();

            Log::info('M-Pesa Query Success', [
                'checkout_id' => $checkoutRequestId,
                'result_code' => $responseData['ResultCode'] ?? 'unknown'
            ]);

            return [
                'success' => true,
                'data' => $responseData
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Network timeout or connection error
            Log::warning('M-Pesa Query Network Timeout', [
                'message' => $e->getMessage(),
                'checkout_id' => $checkoutRequestId
            ]);

            // Return pending status for timeout - don't fail the transaction
            return [
                'success' => true,
                'data' => [
                    'ResultCode' => '1037', // Pending
                    'ResultDesc' => 'Query timeout - transaction may still be processing'
                ]
            ];
        } catch (\Exception $e) {
            Log::error('M-Pesa Query Exception', [
                'message' => $e->getMessage(),
                'checkout_id' => $checkoutRequestId
            ]);

            // Return pending status instead of error
            return [
                'success' => true,
                'data' => [
                    'ResultCode' => '1037', // Pending
                    'ResultDesc' => 'Unable to verify status - please check manually'
                ]
            ];
        }
    }

    /**
     * Process M-Pesa Till Number Payment
     * This is used when customer pays directly to till number
     */
    public function recordTillPayment(array $data)
    {
        // Validate required fields
        if (empty($data['transaction_code']) || empty($data['amount']) || empty($data['phone_number'])) {
            return [
                'success' => false,
                'message' => 'Transaction code, amount, and phone number are required'
            ];
        }

        // In a real scenario, you might want to verify this transaction with M-Pesa API
        // For now, we'll just record it
        return [
            'success' => true,
            'message' => 'Till payment recorded successfully',
            'transaction_code' => $data['transaction_code'],
            'amount' => $data['amount'],
            'phone_number' => $this->formatPhoneNumber($data['phone_number']),
        ];
    }

    /**
     * Process Card Payment
     */
    public function processCardPayment(array $data)
    {
        // This would integrate with a payment gateway like Stripe, Paystack, etc.
        // For now, we'll simulate a successful payment

        $required = ['card_number', 'expiry_month', 'expiry_year', 'cvv', 'amount'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                return [
                    'success' => false,
                    'message' => "Field {$field} is required"
                ];
            }
        }

        // Simulate card processing
        // In production, integrate with payment gateway

        return [
            'success' => true,
            'message' => 'Card payment processed successfully',
            'transaction_id' => 'CARD-' . strtoupper(uniqid()),
            'amount' => $data['amount'],
        ];
    }

    /**
     * Process Bank Transfer
     */
    public function recordBankTransfer(array $data)
    {
        if (empty($data['reference_number']) || empty($data['amount'])) {
            return [
                'success' => false,
                'message' => 'Reference number and amount are required'
            ];
        }

        return [
            'success' => true,
            'message' => 'Bank transfer recorded successfully',
            'reference_number' => $data['reference_number'],
            'amount' => $data['amount'],
        ];
    }

    /**
     * Format phone number to M-Pesa format (254XXXXXXXXX)
     */
    private function formatPhoneNumber($phone)
    {
        // Remove any spaces, dashes, or other characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Remove leading zeros
        $phone = ltrim($phone, '0');

        // Add country code if not present
        if (!str_starts_with($phone, '254')) {
            $phone = '254' . $phone;
        }

        return $phone;
    }

    /**
     * Validate phone number
     */
    public function validatePhoneNumber($phone)
    {
        $formatted = $this->formatPhoneNumber($phone);

        // Kenyan phone numbers should be 12 digits (254XXXXXXXXX)
        if (strlen($formatted) !== 12) {
            return [
                'valid' => false,
                'message' => 'Invalid phone number format. Use 07XX XXX XXX or 254XXX XXX XXX'
            ];
        }

        return [
            'valid' => true,
            'formatted' => $formatted
        ];
    }
}

