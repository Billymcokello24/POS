<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
    protected $activationService;

    public function __construct(PaymentService $paymentService, CmsService $cmsService, \App\Services\SubscriptionActivationService $activationService)
    {
        $this->paymentService = $paymentService;
        $this->cmsService = $cmsService;
        $this->activationService = $activationService;
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
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $business = $user->currentBusiness;
        if (!$business) {
            return response()->json(['success' => false, 'message' => 'Business context required'], 400);
        }

        $planId = $request->input('plan_id');
        $billing = $request->input('billing_cycle');
        $amount = $request->input('amount');
        $phone = $request->input('phone_number');

        // Reference used to track this specific initiation
        $initiationId = strtoupper(uniqid('SUBS_'));

        // Prepare PaymentService data
        $data = [
            'phone_number' => $phone,
            'amount' => $amount,
            // Blueprint: account_reference remains for callback identification
            'account_reference' => "BIZ-{$business->id}-{$initiationId}",
            'transaction_desc' => "Subscription: {$planId} ({$billing})",
        ];

        // CMS Configuration logic (preserving your existing logic for credentials)
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
            foreach (['consumer_secret', 'passkey', 'head_office_passkey', 'initiator_password', 'security_credential'] as $skey) {
                if (!empty($platformMpesa[$skey])) {
                    try {
                        $platformMpesa[$skey] = \Illuminate\Support\Facades\Crypt::decryptString($platformMpesa[$skey]);
                    } catch (\Throwable $e) { /* ignore */ }
                }
            }
            $platformMpesa['simulate'] = isset($platformMpesa['simulate']) ? (bool)$platformMpesa['simulate'] : false;
            $data['mpesa'] = $platformMpesa;
        } else {
            $businessMpesa = $business->mpesa() ?? null;
            if (!empty($businessMpesa)) $data['mpesa'] = $businessMpesa;
        }

        // Credential check
        $creds = $data['mpesa'] ?? null;
        $required = ['consumer_key', 'consumer_secret', 'shortcode', 'passkey'];
        $missing = [];
        if (!is_array($creds)) {
            $missing = $required;
        } else {
            foreach ($required as $k) if (empty($creds[$k])) $missing[] = $k;
        }
        if (!empty($missing)) {
            return response()->json(['success' => false, 'message' => 'M-Pesa credentials missing', 'missing' => $missing], 400);
        }

        // Initiate STK
        $result = $this->paymentService->initiateMpesaStkPush($data);

        $checkout = $result['checkout_request_id'] ?? $result['CheckoutRequestID'] ?? null;
        $merchant = $result['merchant_request_id'] ?? $result['MerchantRequestID'] ?? null;

        // Force simulate fallback logic (preserving your debug features)
        $forceSimulate = config('mpesa.simulate', false) || config('app.debug', false);
        if (!$result['success'] && $forceSimulate) {
            $checkout = 'SIM-CHECKOUT-' . strtoupper(uniqid());
            $merchant = 'SIM-MERCHANT-' . strtoupper(uniqid());
            $result['success'] = true;
        }

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message'] ?? 'Failed to initiate STK'], 400);
        }

        // Persist MpesaPayment as Intent (Financial Source of Truth)
        // Store subscription parameters in metadata so we can create it later
        $mpesaPayment = \App\Models\MpesaPayment::create([
            'business_id' => $business->id,
            'checkout_request_id' => $checkout,
            'merchant_request_id' => $merchant,
            'phone' => $phone,
            'amount' => $amount,
            'account_reference' => $data['account_reference'],
            'status' => 'pending',
            'metadata' => [
                'type' => 'subscription',
                'plan_id' => $planId,
                'billing_cycle' => $billing,
                'initiation_id' => $initiationId,
            ],
            'raw_response' => (array)$result,
        ]);

        $plan = \App\Models\Plan::find($planId);
        $planName = $plan->name ?? 'Unknown';

        // Create INACTIVE subscription record (SaaS Flow Step 3: Intent Record)
        // This tracks the attempt but grants NO access until verified by callback
        $subscription = \App\Models\Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $planId,
            'plan_name' => $planName,
            'amount' => $amount,
            // Status PENDING = Inactive/Unverified
            'status' => \App\Models\Subscription::STATUS_PENDING,
            'is_active' => false,
            'is_verified' => false,
            'payment_method' => 'M-PESA',
            'checkout_request_id' => $checkout,
            'transaction_id' => $initiationId, // Holding ID until confirmed
            'starts_at' => null, // Not started
            'ends_at' => null,
        ]);

        $mpesaPayment->update(['subscription_id' => $subscription->id]);

        // Point 8: Admin visibility via SubscriptionPayment ledger (Intent Phase)
        // Create as PENDING immediately for Admin Oversight
        \App\Models\SubscriptionPayment::create([
            'checkout_request_id' => $checkout,
            'merchant_request_id' => $merchant,
            'subscription_id' => $subscription->id,
            'business_id' => $business->id,
            'business_name' => $business->name,
            'plan_id' => $planId,
            'plan_name' => $planName,
            'billing_cycle' => $billing,
            'status' => 'pending',
            'approval_status' => 'pending',
            'phone' => $phone,
            'amount' => $amount,
            'metadata' => [
                'initiated_at' => now()->toDateTimeString(),
                'type' => 'subscription'
            ]
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'checkout_request_id' => $checkout,
                'merchant_request_id' => $merchant
            ]
        ]);
    }

    /**
     * Finalize subscription activation from STK callback or test scripts.
     * Accepts: subscription_id (optional), checkout_request_id, mpesa_receipt
     */
    public function finalize(Request $request)
    {
        $data = $request->validate([
            'subscription_id' => 'nullable|integer|exists:subscriptions,id',
            'checkout_request_id' => 'nullable|string',
            'mpesa_receipt' => 'nullable|string',
            'phone' => 'nullable|string',
            'amount' => 'nullable|numeric',
        ]);

        $ok = $this->activationService->finalizeFromPayment([
            'checkout_request_id' => $data['checkout_request_id'] ?? null,
            'mpesa_receipt' => $data['mpesa_receipt'] ?? null,
            'phone' => $data['phone'] ?? null,
            'amount' => $data['amount'] ?? null,
        ]);

        if ($ok) {
            return response()->json(['success' => true, 'message' => 'Subscription finalized and activated']);
        }
        
        return response()->json(['success' => false, 'message' => 'Failed to finalize subscription'], 500);
    }

    /**
     * Central activation helper used by jobs/controllers when M-Pesa callback arrives.
     */
    public function finalizeFromCallback(array $data): bool
    {
        return $this->activationService->finalizeFromPayment($data);
    }
}
