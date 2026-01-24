<?php

return [
    /*
    |--------------------------------------------------------------------------
    | M-Pesa Environment
    |--------------------------------------------------------------------------
    |
    | Set to 'sandbox' for testing or 'production' for live transactions
    |
    */
    'environment' => env('MPESA_ENVIRONMENT', 'production'),

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Consumer Key
    |--------------------------------------------------------------------------
    |
    | Your M-Pesa API consumer key from Safaricom developer portal
    |
    */
    'consumer_key' => env('MPESA_CONSUMER_KEY'),

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Consumer Secret
    |--------------------------------------------------------------------------
    |
    | Your M-Pesa API consumer secret from Safaricom developer portal
    |
    */
    'consumer_secret' => env('MPESA_CONSUMER_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Shortcode
    |--------------------------------------------------------------------------
    |
    | Your M-Pesa paybill or till number
    |
    */
    'shortcode' => env('MPESA_SHORTCODE'),

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Head Office Shortcode (For Till Numbers)
    |--------------------------------------------------------------------------
    |
    | For Till Numbers, this is your organization's head office paybill number
    | For PayBill, leave this same as shortcode
    |
    */
    'head_office_shortcode' => env('MPESA_HEAD_OFFICE_SHORTCODE', env('MPESA_SHORTCODE')),

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Passkey
    |--------------------------------------------------------------------------
    |
    | Your M-Pesa STK Push passkey from Safaricom
    |
    */
    'passkey' => env('MPESA_PASSKEY'),

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Callback URL
    |--------------------------------------------------------------------------
    |
    | The URL where M-Pesa will send payment confirmations
    |
    */
    'callback_url' => env('MPESA_CALLBACK_URL', env('APP_URL') . '/api/payments/mpesa/callback'),
    'result_url' => env('MPESA_RESULT_URL', env('MPESA_CALLBACK_URL', env('APP_URL') . '/api/payments/mpesa/callback')),

    // Top-level convenience URLs compatible with template constants; fall back to urls[...] entries below
    'auth_url' => env('MPESA_AUTH_URL', null),
    'stk_push_url' => env('MPESA_STK_PUSH_URL', null),
    'stk_query_url' => env('MPESA_STK_QUERY_URL', null),

    /*
    |--------------------------------------------------------------------------
    | M-Pesa API URLs
    |--------------------------------------------------------------------------
    |
    | Production and Sandbox URLs for M-Pesa API endpoints
    |
    */
    'urls' => [
        'production' => [
            'base_url' => 'https://api.safaricom.co.ke',
            'oauth' => 'https://api.safaricom.co.ke/oauth/v1/generate',
            'stk_push' => 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest',
            'stk_query' => 'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query',
            'c2b_register' => 'https://api.safaricom.co.ke/mpesa/c2b/v2/registerurl',
            'transaction_status' => 'https://api.safaricom.co.ke/mpesa/transactionstatus/v1/query',
            'account_balance' => 'https://api.safaricom.co.ke/mpesa/accountbalance/v1/query',
            'reversal' => 'https://api.safaricom.co.ke/mpesa/reversal/v1/request',
            'b2c' => 'https://api.safaricom.co.ke/mpesa/b2c/v1/paymentrequest',
            'b2b' => 'https://api.safaricom.co.ke/mpesa/b2b/v1/paymentrequest',
        ],
        'sandbox' => [
            'base_url' => 'https://sandbox.safaricom.co.ke',
            'oauth' => 'https://sandbox.safaricom.co.ke/oauth/v1/generate',
            'stk_push' => 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest',
            'stk_query' => 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query',
            'c2b_register' => 'https://sandbox.safaricom.co.ke/mpesa/c2b/v2/registerurl',
            'transaction_status' => 'https://sandbox.safaricom.co.ke/mpesa/transactionstatus/v1/query',
            'account_balance' => 'https://sandbox.safaricom.co.ke/mpesa/accountbalance/v1/query',
            'reversal' => 'https://sandbox.safaricom.co.ke/mpesa/reversal/v1/request',
            'b2c' => 'https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest',
            'b2b' => 'https://sandbox.safaricom.co.ke/mpesa/b2b/v1/paymentrequest',
        ],
    ],

    // Timeout (seconds) for MPESA HTTP requests
    'timeout' => env('MPESA_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Local development helpers
    |--------------------------------------------------------------------------
    |
    | When enabled, STK push requests will be simulated locally without calling
    | Safaricom. Useful for development on localhost or CI environments.
    |
    */
    'simulate' => env('MPESA_SIMULATE', false),

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Initiator Credentials
    |--------------------------------------------------------------------------
    |
    | Credentials for B2C, B2B, and other advanced transactions
    |
    */
    'initiator_name' => env('MPESA_INITIATOR_NAME'),
    'initiator_password' => env('MPESA_INITIATOR_PASSWORD'),
    'security_credential' => env('MPESA_SECURITY_CREDENTIAL'),

    /*
    |--------------------------------------------------------------------------
    | Error messages
    |--------------------------------------------------------------------------
    |
    | Customizable error messages for common MPesa errors
    |
    */
    'errors' => [
        'generic' => env('MPESA_ERROR_GENERIC', 'An error occurred. Please try again.'),
        'network' => env('MPESA_ERROR_NETWORK', 'Network error. Please check your connection.'),
        'invalid_phone' => env('MPESA_ERROR_INVALID_PHONE', 'Invalid phone number format.'),
    ],
];
