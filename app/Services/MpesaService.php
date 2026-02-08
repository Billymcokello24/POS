<?php

namespace App\Services;

use App\Helpers\PhoneFormatter;
use App\Models\Business;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    protected Business $business;

    protected string $baseUrl;

    public function __construct(Business $business)
    {
        $this->business = $business;
        $env = $this->business->mpesa()['environment'] ?? config('mpesa.environment', 'sandbox');
        $this->baseUrl = $env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    protected function token()
    {
        $mpesa = $this->business->mpesa();

        $response = Http::withBasicAuth(
            $mpesa['consumer_key'],
            $mpesa['consumer_secret']
        )->timeout(config('mpesa.timeout', 60))
            ->get($this->baseUrl.'/oauth/v1/generate?grant_type=client_credentials');

        if (! $response->successful()) {
            Log::error('MpesaService::token failed', ['business_id' => $this->business->id, 'body' => $response->body()]);
            throw new \Exception('Failed to obtain M-Pesa access token');
        }

        return $response->json('access_token');
    }

    public function stkPush(string $phone, $amount, ?int $saleId = null, ?string $reference = null, ?string $description = 'Payment')
    {
        $mpesa = $this->business->mpesa();
        if (! $mpesa) {
            throw new \Exception('MPESA not configured for business');
        }

        // If simulate enabled globally or per-business, return a simulated response
        $simulateGlobal = config('mpesa.simulate', false);
        $simulateBusiness = $mpesa['simulate'] ?? false;
        if ($simulateGlobal || $simulateBusiness) {
            $timestamp = now()->format('YmdHis');
            $businessShortcode = $mpesa['shortcode'] ?? '000000';
            $sim = [
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success. Request accepted for processing',
                'MerchantRequestID' => 'SIM-MERCHANT-'.strtoupper(uniqid()),
                'CheckoutRequestID' => 'SIM-CHECKOUT-'.strtoupper(uniqid()),
                'CustomerMessage' => 'Success. Please check your phone to complete payment',
            ];

            $payload = [
                'BusinessShortCode' => $businessShortcode,
                'Password' => base64_encode($businessShortcode.($mpesa['passkey'] ?? '').$timestamp),
                'Timestamp' => $timestamp,
                'Amount' => (int) $amount,
                'PartyA' => PhoneFormatter::toMpesaFormat($phone),
                'PartyB' => $businessShortcode,
                'PhoneNumber' => PhoneFormatter::toMpesaFormat($phone),
                'CallBackURL' => $mpesa['callback_url'] ?? config('mpesa.callback_url'),
                'AccountReference' => ($reference ? "BIZ-{$this->business->id}-".$reference : ($saleId ? "BIZ-{$this->business->id}-SALE-{$saleId}" : "BIZ-{$this->business->id}-".strtoupper(uniqid()))),
                'TransactionDesc' => $description,
            ];

            // Simulated response object with same interface used in controller
            $simResponse = new class($sim) {
                private $data;

                public function __construct($data)
                {
                    $this->data = $data;
                }

                public function successful()
                {
                    return true;
                }

                public function json($key = null)
                {
                    return $this->data;
                }

                public function body()
                {
                    return json_encode($this->data);
                }
            };

            return ['response' => $simResponse, 'payload' => $payload];
        }

        $timestamp = now()->format('YmdHis');

        $businessShortcode = $mpesa['shortcode'];
        $passkey = $mpesa['passkey'];

        $password = base64_encode($businessShortcode.$passkey.$timestamp);

        // Build account reference to include business id for callback identification
        if ($reference) {
            $accountReference = "BIZ-{$this->business->id}-".$reference;
        } elseif ($saleId) {
            $accountReference = "BIZ-{$this->business->id}-SALE-{$saleId}";
        } else {
            $accountReference = "BIZ-{$this->business->id}-".strtoupper(uniqid());
        }

        $payload = [
            'BusinessShortCode' => $businessShortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerBuyGoodsOnline',
            'Amount' => (int) $amount,
            'PartyA' => PhoneFormatter::toMpesaFormat($phone),
            'PartyB' => $businessShortcode,
            'PhoneNumber' => PhoneFormatter::toMpesaFormat($phone),
            'CallBackURL' => $mpesa['callback_url'] ?? config('mpesa.callback_url'),
            'AccountReference' => $accountReference,
            'TransactionDesc' => $description,
        ];

        $response = Http::withToken($this->token())
            ->timeout(config('mpesa.timeout', 60))
            ->post($this->baseUrl.'/mpesa/stkpush/v1/processrequest', $payload);

        return ['response' => $response, 'payload' => $payload];
    }

    public function queryStkStatus(string $checkoutRequestId)
    {
        $mpesa = $this->business->mpesa();
        if (!$mpesa) {
            throw new \Exception('MPESA not configured for business');
        }

        // Handle simulation
        if (config('mpesa.simulate', false) || ($mpesa['simulate'] ?? false)) {
            return [
                'ResponseCode' => '0',
                'ResponseDescription' => 'The service request has been accepted successfully',
                'MerchantRequestID' => 'SIM-MERCHANT-' . strtoupper(uniqid()),
                'CheckoutRequestID' => $checkoutRequestId,
                'ResultCode' => '0',
                'ResultDesc' => 'The service request is processed successfully.'
            ];
        }

        $timestamp = now()->format('YmdHis');
        $businessShortcode = $mpesa['shortcode'];
        $passkey = $mpesa['passkey'];
        $password = base64_encode($businessShortcode . $passkey . $timestamp);

        $payload = [
            'BusinessShortCode' => $businessShortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ];

        $response = Http::withToken($this->token())
            ->timeout(config('mpesa.timeout', 60))
            ->post($this->baseUrl . '/mpesa/stkpushquery/v1/query', $payload);

        return $response->json();
    }
}
