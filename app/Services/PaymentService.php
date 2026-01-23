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
        // Optional per-business MPESA credentials
        $mpesaCreds = $data['mpesa'] ?? null;

        // Helper to fetch setting or fallback to config
        $get = function ($key, $default = null) use ($mpesaCreds) {
            if ($mpesaCreds && array_key_exists($key, $mpesaCreds) && !empty($mpesaCreds[$key])) {
                return $mpesaCreds[$key];
            }
            return config('mpesa.' . $key, $default);
        };

        $phoneNumber = $this->formatPhoneNumber($data['phone_number']);
        $amount = (int) $data['amount'];
        $accountReference = $data['account_reference'] ?? 'POS-SALE';
        $transactionDesc = $data['transaction_desc'] ?? 'Payment for goods';

        // Get M-Pesa credentials from per-business settings or config
        $consumerKey = $get('consumer_key');
        $consumerSecret = $get('consumer_secret');
        $shortcode = $get('shortcode');
        $headOfficeShortcode = $get('head_office_shortcode');
        $passkey = $get('passkey');
        $callbackUrl = $get('callback_url', config('mpesa.callback_url'));
        $environment = $get('environment', config('mpesa.environment', 'production'));

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

            // Build mapping attempts: prefer head-office-as-business then store-as-business
            $attempts = [];
            if (!empty($headOfficeShortcode)) {
                $attempts[] = ['label' => 'head_office_as_business', 'business_shortcode' => $headOfficeShortcode, 'party_b' => $shortcode];
                $attempts[] = ['label' => 'store_as_business', 'business_shortcode' => $shortcode, 'party_b' => $headOfficeShortcode];
            } else {
                $attempts[] = ['label' => 'store_as_business', 'business_shortcode' => $shortcode, 'party_b' => $shortcode];
            }

            $lastError = null;
            foreach ($attempts as $attempt) {
                $businessShortCode = $attempt['business_shortcode'];
                $partyB = $attempt['party_b'];
                $password = base64_encode($businessShortCode . $passkey . $timestamp);

                Log::info('Initiating M-Pesa STK Push (attempt)', [
                    'attempt' => $attempt['label'],
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

                // Make the STK push request for this attempt
                $stkResponse = Http::timeout(30)
                    ->retry(2, 100)
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

                if ($stkResponse->successful()) {
                    $responseData = $stkResponse->json();
                    if (is_array($responseData) && isset($responseData['ResponseCode']) && $responseData['ResponseCode'] === '0') {
                        // Success: return including which mapping succeeded
                        return [
                            'success' => true,
                            'message' => 'STK Push sent successfully',
                            'checkout_request_id' => $responseData['CheckoutRequestID'] ?? null,
                            'merchant_request_id' => $responseData['MerchantRequestID'] ?? null,
                            'response_code' => $responseData['ResponseCode'] ?? null,
                            'response_description' => $responseData['ResponseDescription'] ?? null,
                            'customer_message' => $responseData['CustomerMessage'] ?? 'Please check your phone to complete payment',
                            'used_mapping' => $attempt['label'],
                            'used_business_shortcode' => $businessShortCode,
                            'used_party_b' => $partyB,
                            'used_passkey' => $passkey,
                        ];
                    }
                }

                // capture error body for this attempt
                try {
                    $body = $stkResponse->json();
                } catch (\Exception $e) {
                    $body = ['status' => $stkResponse->status(), 'body' => $stkResponse->body()];
                }

                Log::warning('M-Pesa STK Push Attempt Failed', ['attempt' => $attempt['label'], 'status' => $stkResponse->status(), 'response' => $body]);
                $lastError = $body;
            }

            // If we got here, all attempts failed
            $friendly = 'Failed to initiate M-Pesa payment';
            if (is_array($lastError)) {
                if (isset($lastError['errorCode']) || isset($lastError['errorMessage'])) {
                    $code = $lastError['errorCode'] ?? 'unknown';
                    $msg = $lastError['errorMessage'] ?? ($lastError['error'] ?? null);
                    $friendly = "M-Pesa error ({$code}): " . ($msg ?? json_encode($lastError));
                } elseif (isset($lastError['ResponseDescription'])) {
                    $friendly = $lastError['ResponseDescription'];
                }
            }

            Log::error('M-Pesa STK Push All Attempts Failed', ['last_error' => $lastError]);
            return [
                'success' => false,
                'message' => $friendly,
                'error' => $lastError
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
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Try to extract response body if available
            $body = null;
            try {
                if (method_exists($e, 'response') && $e->response) {
                    $res = $e->response;
                    // Attempt to decode JSON
                    $body = json_decode($res->body(), true) ?: $res->body();
                }
            } catch (\Exception $inner) {
                $body = $e->getMessage();
            }

            Log::error('M-Pesa STK Push RequestException', ['message' => $e->getMessage(), 'body' => $body]);

            $friendly = 'An error occurred while processing M-Pesa payment';
            if (is_array($body)) {
                if (!empty($body['errorCode']) || !empty($body['errorMessage'])) {
                    $friendly = 'M-Pesa error (' . ($body['errorCode'] ?? 'unknown') . '): ' . ($body['errorMessage'] ?? json_encode($body));
                } elseif (!empty($body['CustomerMessage'])) {
                    $friendly = $body['CustomerMessage'];
                }
            } elseif (is_string($body)) {
                $friendly = $body;
            }

            return [
                'success' => false,
                'message' => $friendly,
                'error' => $body
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
    public function queryStkPushStatus($checkoutRequestId, array $credentials = null)
    {
        // Allow per-business credentials
        $get = function ($key, $default = null) use ($credentials) {
            if ($credentials && array_key_exists($key, $credentials) && !empty($credentials[$key])) {
                return $credentials[$key];
            }
            return config('mpesa.' . $key, $default);
        };

        $consumerKey = $get('consumer_key');
        $consumerSecret = $get('consumer_secret');
        $shortcode = $get('shortcode');
        $passkey = $get('passkey');
        $environment = $get('environment', config('mpesa.environment', 'production'));

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

