<?php

namespace App\Services\Http\Controllers\Api;

use App\Services\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\PaymentService;
use App\Services\CmsService;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SubscriptionPaymentController extends Controller
{
    protected $paymentService;
    protected $cmsService;

    public function __construct(PaymentService $paymentService, CmsService $cmsService)
    {
        $this->paymentService = $paymentService;
        $this->cmsService = $cmsService;
    }

    /**
     * Initiate STK Push for a subscription
     */
    public function initiate(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'phone_number' => 'required|string',
            'amount' => 'required|numeric|min:1',
        ]);

        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $business = $user->currentBusiness;
        if (! $business) {
            return response()->json(['success' => false, 'message' => 'Business context required'], 400);
        }

        $planId = $request->input('plan_id');
        $billing = $request->input('billing_cycle');
        $amount = $request->input('amount');
        $phone = $request->input('phone_number');

        // create pending subscription
        $subscription = Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $planId,
            'plan_name' => '', // frontend provided plan name not required here
            'amount' => $amount,
            'currency' => $business->currency ?? 'KES',
            'status' => 'pending',
            'payment_method' => 'MPESA_STK',
            'payment_details' => ['phone' => $phone, 'billing_cycle' => $billing],
        ]);

        // Prepare PaymentService data, prefer CMS mpesa config
        $data = [
            'phone_number' => $phone,
            'amount' => $amount,
            'account_reference' => 'SUB-' . $subscription->id,
            'transaction_desc' => 'Subscription payment',
        ];

        $cms = $this->cmsService->getContent();
        $cmsArr = [];
        if (is_string($cms)) {
            $decoded = json_decode($cms, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $cmsArr = $decoded;
        } elseif (is_array($cms)) {
            $cmsArr = $cms;
        } elseif (is_object($cms)) {
            $cmsArr = (array) $cms;
        }

        $platformMpesa = $cmsArr['mpesa'] ?? null;
        if ($platformMpesa && is_array($platformMpesa)) {
            // attempt decrypt (CmsController stored encrypted sensitive values)
            foreach (['consumer_secret','passkey','head_office_passkey','initiator_password','security_credential'] as $skey) {
                if (!empty($platformMpesa[$skey])) {
                    try { $platformMpesa[$skey] = \Illuminate\Support\Facades\Crypt::decryptString($platformMpesa[$skey]); } catch (\Throwable $e) { /* ignore */ }
                }
            }
            $platformMpesa['simulate'] = isset($platformMpesa['simulate']) ? (bool)$platformMpesa['simulate'] : false;
            $data['mpesa'] = $platformMpesa;
        } else {
            // fallback to business mpesa settings
            $businessMpesa = $business->mpesa() ?? null;
            if (! empty($businessMpesa)) $data['mpesa'] = $businessMpesa;
        }

        // Basic credential check
        $creds = $data['mpesa'] ?? null;
        $required = ['consumer_key','consumer_secret','shortcode','passkey'];
        $missing = [];
        if (! is_array($creds)) {
            $missing = $required;
        } else {
            foreach ($required as $k) if (empty($creds[$k])) $missing[] = $k;
        }
        if (! empty($missing)) {
            Log::warning('Subscription STK missing credentials', ['missing' => $missing, 'business_id' => $business->id]);
            return response()->json(['success' => false, 'message' => 'M-Pesa credentials missing for subscription STK', 'missing' => $missing], 400);
        }

        // Initiate STK
        $result = $this->paymentService->initiateMpesaStkPush($data);

        // Normalize checkout/merchant ids from various payment service response shapes
        $checkout = null;
        $merchant = null;
        // direct keys
        if (!empty($result['checkout_request_id'])) $checkout = $result['checkout_request_id'];
        if (!empty($result['merchant_request_id'])) $merchant = $result['merchant_request_id'];
        // common capitalized keys from some MPESA responses
        if (empty($checkout) && !empty($result['CheckoutRequestID'])) $checkout = $result['CheckoutRequestID'];
        if (empty($merchant) && !empty($result['MerchantRequestID'])) $merchant = $result['MerchantRequestID'];
        // nested response object/array (some PaymentService return the raw response under 'response')
        if (empty($checkout) && !empty($result['response']) && is_array($result['response']) && !empty($result['response']['CheckoutRequestID'])) $checkout = $result['response']['CheckoutRequestID'];
        if (empty($merchant) && !empty($result['response']) && is_array($result['response']) && !empty($result['response']['MerchantRequestID'])) $merchant = $result['response']['MerchantRequestID'];
        // some services return an object under 'response'
        if (empty($checkout) && !empty($result['response']) && is_object($result['response'])) {
            try {
                if (property_exists($result['response'], 'CheckoutRequestID')) $checkout = $result['response']->CheckoutRequestID;
                if (property_exists($result['response'], 'MerchantRequestID')) $merchant = $result['response']->MerchantRequestID;
            } catch (\Throwable $e) { /* ignore */ }
        }

        // If the call failed but we're in debug or forced-simulate mode, create a simulated response so frontend can continue in dev
        // Use configuration flags instead of env() to decide forced simulation
        $forceSimulate = config('mpesa.simulate', false) || config('app.debug', false);
        if (! $result['success'] && $forceSimulate) {
            Log::warning('PaymentService failed but falling back to simulated STK because MPESA_FORCE_SIMULATE or APP_DEBUG is enabled', ['result' => $result, 'subscription_id' => $subscription->id]);

            $simulated = [
                'CheckoutRequestID' => 'SIM-CHECKOUT-' . strtoupper(uniqid()),
                'MerchantRequestID' => 'SIM-MERCHANT-' . strtoupper(uniqid()),
                'ResponseCode' => '0',
                'ResponseDescription' => 'Simulated success',
                'CustomerMessage' => 'Simulated: Please check your phone to complete payment',
            ];

            // Persist simulated subscription payment
            try {
                SubscriptionPayment::create([
                    'subscription_id' => $subscription->id,
                    'business_id' => $business->id,
                    'checkout_request_id' => $simulated['CheckoutRequestID'],
                    'merchant_request_id' => $simulated['MerchantRequestID'],
                    'mpesa_receipt' => null,
                    'phone' => $phone,
                    'amount' => $amount,
                    'status' => 'pending',
                    'metadata' => ['simulated' => true],
                    'raw_response' => $simulated,
                ]);

                // Also create simulated MpesaPayment for logging
                \App\Models\MpesaPayment::create([
                    'business_id' => $business->id,
                    'sale_id' => null,
                    'checkout_request_id' => $simulated['CheckoutRequestID'],
                    'merchant_request_id' => $simulated['MerchantRequestID'],
                    'phone' => $phone,
                    'amount' => $amount,
                    'reference' => null,
                    'account_reference' => $data['account_reference'],
                    'status' => 'pending',
                    'raw_response' => $simulated,
                    'metadata' => ['simulated' => true],
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to persist simulated subscription_payment', ['error' => $e->getMessage()]);
            }

            return response()->json(['success' => true, 'data' => ['checkout_request_id' => $simulated['CheckoutRequestID'], 'merchant_request_id' => $simulated['MerchantRequestID'], 'simulated' => true]]);
        }

        // Log safe result
        try {
            $safe = $result;
            if (isset($safe['response']) && is_object($safe['response'])) {
                try { $safe['response_body'] = method_exists($safe['response'],'json') ? $safe['response']->json() : (method_exists($safe['response'],'body') ? $safe['response']->body() : null); } catch (\Throwable $e) {}
                unset($safe['response']);
            }
            Log::info('Subscription STK initiated', ['result' => $safe, 'subscription_id' => $subscription->id, 'business_id' => $business->id]);
        } catch (\Throwable $e) { /* ignore */ }

        if (! $result['success']) {
            $payload = ['success' => false, 'message' => $result['message'] ?? 'Failed to initiate STK'];
            if (config('app.debug')) $payload['debug'] = $result;
            return response()->json($payload, 400);
        }

        // Persist SubscriptionPayment
        try {
            // use normalized values if available, otherwise attempt to fallback to result keys
            $checkout = $checkout ?? ($result['checkout_request_id'] ?? null);
            $merchant = $merchant ?? ($result['merchant_request_id'] ?? null);
             SubscriptionPayment::create([
                 'subscription_id' => $subscription->id,
                 'business_id' => $business->id,
                 'checkout_request_id' => $checkout,
                 'merchant_request_id' => $merchant,
                 'mpesa_receipt' => null,
                 'phone' => $phone,
                 'amount' => $amount,
                 'status' => 'pending',
                 'metadata' => $result['payload'] ?? null,
                 'raw_response' => is_array($result['response']) ? $result['response'] : (is_array($result) ? ($result['response'] ?? null) : null),
             ]);

            // Also create MpesaPayment record for logging (independent from sales)
            \App\Models\MpesaPayment::create([
                 'business_id' => $business->id,
                 'sale_id' => null,
                 'checkout_request_id' => $checkout,
                 'merchant_request_id' => $merchant,
                 'phone' => $phone,
                 'amount' => $amount,
                 'reference' => null,
                 'account_reference' => $data['account_reference'],
                 'status' => 'pending',
                 'raw_response' => is_array($result['response']) ? $result['response'] : (is_array($result) ? ($result['response'] ?? null) : null),
                 'metadata' => $result['payload'] ?? null,
             ]);

            // Persist transaction id on the subscription for reliable matching by callbacks/reconcile
            try {
                if (! empty($checkout)) {
                    $subscription->transaction_id = $checkout;
                    $subscription->save();
                }
            } catch (\Throwable $_) { /* non-fatal */ }
         } catch (\Throwable $e) {
             Log::error('Failed to create subscription_payment or mpesa_payment', ['error' => $e->getMessage()]);
         }

        return response()->json(['success' => true, 'data' => ['checkout_request_id' => $checkout ?? null, 'merchant_request_id' => $merchant ?? null]]);
     }
 }
