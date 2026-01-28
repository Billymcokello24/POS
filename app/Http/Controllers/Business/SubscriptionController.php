<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Payment;
use App\Services\CmsService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    protected PaymentService $paymentService;
    protected CmsService $cmsService;

    public function __construct(PaymentService $paymentService, CmsService $cmsService)
    {
        $this->paymentService = $paymentService;
        $this->cmsService = $cmsService;
    }

    /**
     * Display subscription plans and current status
     */
    public function index()
    {
        $business = Auth::user()->currentBusiness;

        if (! $business) {
            if (Auth::user()->is_super_admin) {
                return redirect()->route('admin.dashboard')->with('error', 'Super Admins manage subscriptions globally.');
            }

            return redirect()->route('dashboard')->with('error', 'No business context found.');
        }

        // Proactive activation: if there are any subscriptions in 'initiated' or 'pending' state
        // but a completed SubscriptionPayment or successful MpesaPayment already exists, attempt activation now.
        try {
            // Include pending_verification as well: retry activation for subscriptions already flagged for manual verification
            $candidates = $business->subscriptions()->whereIn('status', ['initiated','pending','pending_verification'])->get();
            foreach ($candidates as $cand) {
                try {
                    // Check for a completed SubscriptionPayment
                    $sp = \App\Models\SubscriptionPayment::where('subscription_id', $cand->id)->whereIn('status', ['completed','completed'])->first();
                    if (! $sp) {
                        // Check MpesaPayment by checkout or account reference
                        $mp = null;
                        if (! empty($cand->transaction_id)) {
                            $mp = \App\Models\MpesaPayment::where('checkout_request_id', $cand->transaction_id)->orWhere('receipt', $cand->transaction_id)->where('business_id', $business->id)->first();
                        }
                        // also try by SUB-<id> account reference
                        if (! $mp) {
                            $mp = \App\Models\MpesaPayment::where('account_reference', 'SUB-'.$cand->id)->where('business_id', $business->id)->first();
                        }

                        if ($mp && ($mp->status === 'success' || $mp->status === 'completed')) {
                            // Create subscription payment record if missing
                            \App\Models\SubscriptionPayment::firstOrCreate([
                                'subscription_id' => $cand->id,
                                'checkout_request_id' => $mp->checkout_request_id,
                            ], [
                                'business_id' => $business->id,
                                'merchant_request_id' => $mp->merchant_request_id ?? null,
                                'mpesa_receipt' => $mp->receipt ?? null,
                                'phone' => $mp->phone ?? null,
                                'amount' => $mp->amount ?? $cand->amount,
                                'status' => 'completed',
                                'metadata' => $mp->metadata ?? null,
                                'raw_response' => $mp->raw_response ?? null,
                            ]);

                            // Attempt activation
                            if (method_exists($cand->business, 'activateSubscription')) {
                                $cand->business->activateSubscription($cand, $mp->receipt ?? $mp->checkout_request_id, ['source' => 'proactive_index_activation']);
                            }
                        }
                    } else {
                        // If SubscriptionPayment exists and is completed, activate
                        if (method_exists($cand->business, 'activateSubscription')) {
                            $cand->business->activateSubscription($cand, $sp->mpesa_receipt ?? $sp->checkout_request_id, ['source' => 'proactive_index_activation']);
                        }
                    }
                } catch (\Throwable $_) {
                    // ignore per-subscription activation errors — we'll still render page
                }
            }
        } catch (\Throwable $_) {
            // ignore
        }

        $plans = Plan::where('is_active', true)->with('features')->get();
        $currentSubscription = $business->activeSubscription;
        // Show only subscriptions that passed payment and are awaiting admin verification
        $pendingSubscription = $business->subscriptions()->where('status', 'pending_verification')->latest()->first();

        return Inertia::render('Subscription/Index', [
            'plans' => $plans,
            'currentSubscription' => $currentSubscription,
            'pendingSubscription' => $pendingSubscription,
            'business' => $business->only('id', 'name', 'plan_id'),
        ]);
    }

    /**
     * Initiate M-Pesa Payment for a Plan (STK Push or Till)
     */
    public function initiatePayment(Request $request)
    {
        // Detect Inertia requests explicitly — Inertia sends X-Inertia header.
        $isInertia = (bool) $request->header('X-Inertia');
        // Treat as API request only when client expects JSON or is AJAX and it's NOT an Inertia request.
        // This avoids returning plain JSON to X-Inertia requests (which expect Inertia responses).
        $isApiRequest = ! $isInertia && ($request->wantsJson() || $request->ajax());

        // If this is a finalization call (frontend is posting mpesa_receipt after successful STK),
        // validate only necessary fields and skip phone_number requirement.
        if ($request->filled('mpesa_receipt')) {
            $request->validate([
                'plan_id' => 'required|exists:plans,id',
                'mpesa_receipt' => 'required|string',
            ]);
        } else {
            $request->validate([
                'plan_id' => 'required|exists:plans,id',
                'phone_number' => 'required_if:payment_method,stk|string',
                'billing_cycle' => 'required|in:monthly,yearly',
                'payment_method' => 'required|in:stk,till,card,bank_transfer,cash',
                'transaction_code' => 'required_if:payment_method,till|string|min:10',
                'card_number' => 'required_if:payment_method,card|string|size:16',
                'expiry_month' => 'required_if:payment_method,card|integer|min:1|max:12',
                'expiry_year' => 'required_if:payment_method,card|integer|min:' . date('Y'),
                'cvv' => 'required_if:payment_method,card|string|size:3',
                'cardholder_name' => 'required_if:payment_method,card|string',
                'reference_number' => 'required_if:payment_method,bank_transfer|string',
                'bank_name' => 'nullable|string',
                'account_number' => 'nullable|string',
                'received_amount' => 'required_if:payment_method,cash|numeric|min:0',
            ]);
        }

        $business = Auth::user()->currentBusiness;
        if (! $business) {
            return back()->with('error', 'Business context required for payments.');
        }

        $plan = Plan::findOrFail($request->plan_id);
        $amount = $request->billing_cycle === 'monthly' ? $plan->price_monthly : $plan->price_yearly;

        // If this request is a finalization (receipt present), activate pending subscription
        if ($request->filled('mpesa_receipt')) {
            $mpesaReceipt = $request->input('mpesa_receipt');
            // find pending subscription for this business and plan
            $pending = Subscription::where('business_id', $business->id)
                ->where('plan_id', $plan->id)
                ->whereIn('status', ['initiated', 'pending'])
                ->latest()
                ->first();

            if (! $pending) {
                if ($isApiRequest) {
                    return response()->json(['success' => false, 'message' => 'No pending subscription found to finalize.'], 404);
                }

                return back()->with('error', 'No pending subscription found to finalize.');
            }

            try {
                // record the transaction id on the pending subscription
                $pending->update(['transaction_id' => $mpesaReceipt, 'mpesa_receipt' => $mpesaReceipt, 'verified_at' => now()]);

                // Ensure a Payment record exists for this checkout/receipt so the system treats it as recorded by Safaricom
                $payment = \App\Models\Payment::where('reference_number', $mpesaReceipt)->orWhere('metadata->checkout_request_id', $mpesaReceipt)->first();
                if (! $payment) {
                    // Skip creating top-level payments table entry for subscription finalization.
                    // The subscription payment lifecycle is tracked in subscription_payments.
                    Log::info('Subscription finalization: skipping payments table insert, subscription_payments will be used', ['subscription_id' => $pending->id, 'receipt' => $mpesaReceipt]);
                }

                // Also create/update the SubscriptionPayment to reflect completion
                try {
                    $sp = \App\Models\SubscriptionPayment::where('checkout_request_id', $mpesaReceipt)->orWhere('mpesa_receipt', $mpesaReceipt)->first();
                    if (! $sp) {
                        \App\Models\SubscriptionPayment::create([
                            'subscription_id' => $pending->id,
                            'business_id' => $pending->business_id,
                            'checkout_request_id' => $mpesaReceipt,
                            'merchant_request_id' => null,
                            'mpesa_receipt' => $mpesaReceipt,
                            'phone' => $pending->payment_details['phone'] ?? null,
                            'amount' => $pending->amount,
                            'status' => 'completed',
                            'metadata' => ['source' => 'frontend_finalization'],
                            'raw_response' => null,
                        ]);
                    } else {
                        $sp->update(['status' => 'completed', 'mpesa_receipt' => $mpesaReceipt]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to create/update SubscriptionPayment on finalization', ['error' => $e->getMessage()]);
                }

                // Attempt backend-driven activation now that we have confirmed payment
                try {
                    if (method_exists($pending->business, 'activateSubscription')) {
                        $pending->business->activateSubscription($pending, $mpesaReceipt, ['source' => 'business_finalize']);

                        // Push SSE event to inform clients of activation
                        try { \App\Services\SseService::pushBusinessEvent($pending->business_id, 'subscription.activated', ['id' => $pending->id, 'status' => 'active']); } catch (\Throwable $_) {}

                        if ($isApiRequest) {
                            return response()->json(['success' => true, 'message' => 'Subscription activated successfully', 'data' => ['receipt' => $mpesaReceipt]]);
                        }

                        return back()->with('success', 'Subscription activated successfully.');
                    }
                } catch (\Throwable $e) {
                    Log::error('Business finalize activation failed: '.$e->getMessage());
                    // fall through to pending_verification fallback
                }

                // Fallback: mark subscription as pending_verification so admins can review
                try {
                    $pending->update(['status' => 'pending_verification']);
                    try { \App\Services\SseService::pushBusinessEvent($pending->business_id, 'subscription.finalized', ['id' => $pending->id, 'status' => $pending->status]); } catch (\Throwable $_) {}
                } catch (\Throwable $_) {}

                if ($isApiRequest) {
                    return response()->json(['success' => true, 'message' => 'Subscription recorded and awaiting verification', 'data' => ['receipt' => $mpesaReceipt]]);
                }

                return back()->with('success', 'Subscription recorded and awaiting verification.');
            } catch (Throwable $e) {
                Log::error('Subscription finalization failed', ['error' => $e->getMessage()]);
                if ($isApiRequest) {
                    return response()->json(['success' => false, 'message' => 'Failed to activate subscription', 'error' => $e->getMessage()], 500);
                }

                return back()->with('error', 'Failed to activate subscription.');
            }
        }

        // M-Pesa STK (instant push)
        if ($request->payment_method === 'stk') {
            // Prepare data for PaymentService; prefer platform MPESA (from CMS) over business settings
            $data = [
                'phone_number' => $request->phone_number,
                'amount' => $amount,
                // account_reference set after subscription created to include subscription id
                'transaction_desc' => "Payment for {$plan->name} [{$request->billing_cycle}]",
            ];

            // Use platform-level MPESA config from CmsService (handles decryption)
            $platformMpesa = $this->cmsService->getMpesaConfig();
            if (is_array($platformMpesa) && !empty($platformMpesa)) {
                $data['mpesa'] = $platformMpesa;
            }

            // Create pending subscription BEFORE initiating STK so we can include subscription id in AccountReference
            $subscription = Subscription::create([
                'business_id' => $business->id,
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'amount' => $amount,
                'currency' => 'KES',
                'status' => 'initiated', // awaiting payment confirmation via STK callback
                'transaction_id' => null,
                'payment_method' => 'MPESA_STK',
                'payment_details' => [
                    'phone' => $request->phone_number,
                    'billing_cycle' => $request->billing_cycle,
                ],
            ]);

            // Ensure account reference contains subscription id for mapping in callbacks
            $accountRef = 'SUB-'.($subscription->id ?? strtoupper(uniqid()));
            $data['account_reference'] = $accountRef;

            // If platform MPESA config exists, pass it explicitly so PaymentService uses CMS credentials
            if (!empty($platformMpesa) && is_array($platformMpesa)) {
                $data['mpesa'] = $platformMpesa;
            }

            // Also allow business mpesa fallback if present in business settings
            $businessMpesa = $business->mpesa() ?? null;
            if (empty($data['mpesa']) && ! empty($businessMpesa)) {
                $data['mpesa'] = $businessMpesa;
            }

            // Basic credential presence check and preflight auth to give clearer errors before remote calls
            $creds = $data['mpesa'] ?? null;
            $requiredKeys = ['consumer_key', 'consumer_secret', 'shortcode', 'passkey'];
            $missing = [];
            if (! is_array($creds)) {
                $missing = $requiredKeys; // everything missing
            } else {
                foreach ($requiredKeys as $k) {
                    if (empty($creds[$k])) $missing[] = $k;
                }
            }

            if (! empty($missing)) {
                // Log without secrets; indicate which keys are missing
                Log::warning('Subscription STK aborted: missing MPESA credentials for subscription flow', [
                    'missing_keys' => $missing,
                    'business_id' => $business->id,
                    'use_platform_mpesa' => isset($platformMpesa) && is_array($platformMpesa),
                ]);

                $userMessage = 'M-Pesa credentials are not configured for subscription payments. Please ask the super admin to set MPESA defaults in CMS or configure business MPESA settings.';
                if ($isApiRequest) {
                    return response()->json(['success' => false, 'message' => $userMessage, 'error' => 'missing_credentials', 'missing' => $missing], 400);
                }
                return back()->with('error', $userMessage);
            }

            // Pre-flight OAuth check: verify token can be obtained to surface clear errors early.
            if (is_array($creds)) {
                try {
                    $env = $creds['environment'] ?? config('mpesa.environment', 'production');
                    $urls = config("mpesa.urls.{$env}");
                    $authUrl = config('mpesa.auth_url') ?: ($urls['oauth'] ?? null);
                    if ($authUrl && !empty($creds['consumer_key']) && !empty($creds['consumer_secret'])) {
                        $authResp = \Illuminate\Support\Facades\Http::withBasicAuth($creds['consumer_key'], $creds['consumer_secret'])
                            ->timeout(10)
                            ->get($authUrl . (str_contains($authUrl, '?') ? '&' : '?') . 'grant_type=client_credentials');

                        if (! $authResp->successful()) {
                            $errorId = 'mpesa_err_' . uniqid();
                            $body = null;
                            try { $body = $authResp->body(); } catch (\Throwable $_) { $body = null; }
                            Log::error('Subscription STK auth preflight failed (business flow)', ['error_id' => $errorId, 'status' => method_exists($authResp,'status') ? $authResp->status() : null, 'body_snippet' => $body ? substr($body,0,1000) : null, 'business_id' => $business->id]);

                            // If simulation enabled, fall back to simulated STK as a development convenience
                            $platformSim = isset($creds['simulate']) ? (bool)$creds['simulate'] : false;
                            $globalSim = config('mpesa.simulate', false);
                            if ($platformSim || $globalSim) {
                                Log::warning('Auth failed but simulation enabled (business flow) — creating simulated STK', ['error_id' => $errorId, 'business_id' => $business->id]);
                                $simulated = [
                                    'CheckoutRequestID' => 'SIM-CHECKOUT-' . strtoupper(uniqid()),
                                    'MerchantRequestID' => 'SIM-MERCHANT-' . strtoupper(uniqid()),
                                    'ResponseCode' => '0',
                                    'ResponseDescription' => 'Simulated success',
                                    'CustomerMessage' => 'Simulated: Please check your phone to complete payment',
                                ];
                                try {
                                    SubscriptionPayment::create([
                                        'subscription_id' => $subscription->id,
                                        'business_id' => $business->id,
                                        'checkout_request_id' => $simulated['CheckoutRequestID'],
                                        'merchant_request_id' => $simulated['MerchantRequestID'],
                                        'mpesa_receipt' => null,
                                        'phone' => $request->phone_number,
                                        'amount' => $amount,
                                        'status' => 'pending',
                                        'metadata' => ['simulated' => true],
                                        'raw_response' => $simulated,
                                    ]);
                                    \App\Models\MpesaPayment::create([
                                        'business_id' => $business->id,
                                        'sale_id' => null,
                                        'checkout_request_id' => $simulated['CheckoutRequestID'],
                                        'merchant_request_id' => $simulated['MerchantRequestID'],
                                        'phone' => $request->phone_number,
                                        'amount' => $amount,
                                        'reference' => null,
                                        'account_reference' => $data['account_reference'],
                                        'status' => 'pending',
                                        'raw_response' => $simulated,
                                        'metadata' => ['simulated' => true],
                                    ]);
                                } catch (\Throwable $e) {
                                    Log::error('Failed to persist simulated subscription_payment (business flow)', ['error' => $e->getMessage()]);
                                }
                                $dataOut = ['success'=>true,'data'=>['checkout_request_id'=>$simulated['CheckoutRequestID'],'merchant_request_id'=>$simulated['MerchantRequestID'],'simulated'=>true]];
                                return $isApiRequest ? response()->json($dataOut) : back()->with('success','M-Pesa prompt (simulated) sent.');
                            }

                            return $isApiRequest ? response()->json(['success'=>false,'message'=>'Failed to authenticate with M-Pesa using configured credentials.','error_id'=>$errorId,'body_snippet'=>$body],400) : back()->with('error','Failed to authenticate with M-Pesa using configured credentials.');
                        }
                    }
                } catch (\Exception $e) {
                    $errorId = 'mpesa_err_' . uniqid();
                    Log::error('Subscription STK auth preflight exception (business flow)', ['error_id'=>$errorId,'message'=>$e->getMessage(),'business_id'=>$business->id]);
                    return $isApiRequest ? response()->json(['success'=>false,'message'=>'Error while validating M-Pesa credentials','error_id'=>$errorId],500) : back()->with('error','Error while validating M-Pesa credentials');
                }
            }

            $result = $this->paymentService->initiateMpesaStkPush($data);

            // Debug log: capture the full payment service result to help diagnose failures
            try {
                // Avoid logging sensitive credential values
                $safeResult = $result;
                if (isset($safeResult['response']) && is_object($safeResult['response'])) {
                    // if it's an Http client response, capture status/body if possible
                    try {
                        $safeResult['response_body'] = method_exists($safeResult['response'], 'json') ? $safeResult['response']->json() : (method_exists($safeResult['response'], 'body') ? $safeResult['response']->body() : null);
                        if (isset($safeResult['response_body'])) unset($safeResult['response']);
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
                Log::debug('Subscription STK raw result', ['result' => $safeResult, 'business_id' => $business->id, 'subscription_temp_id' => $subscription->id ?? null]);
            } catch (\Throwable $e) {
                Log::debug('Failed to log PaymentService result: ' . $e->getMessage());
            }

            if (! $result['success']) {
                $userMessage = $result['message'] ?? 'Failed to initiate M-Pesa STK push.';

                // Log full result for debugging (avoid logging secrets). Include payload shape and which creds source was used.
                try {
                    $logPayload = [
                        'business_id' => $business->id,
                        'subscription_temp_id' => $subscription->id ?? null,
                        'checkout' => $result['checkout_request_id'] ?? null,
                        'merchant_request_id' => $result['merchant_request_id'] ?? null,
                        'message' => $userMessage,
                        'result_meta' => array_filter([
                            'used_mapping' => $result['used_mapping'] ?? null,
                            'used_business_shortcode' => $result['used_business_shortcode'] ?? null,
                        ]),
                    ];
                    Log::error('Subscription STK initiation failed', $logPayload);
                } catch (\Throwable $e) {
                    Log::error('Subscription STK initiation failed; additionally failed to compose log: ' . $e->getMessage());
                }

                if ($isApiRequest) {
                    $response = ['success' => false, 'message' => $userMessage, 'error' => $result['error'] ?? null];
                    if (config('app.debug')) {
                        // In debug mode include the payment service result for troubleshooting (may contain non-sensitive keys)
                        $response['debug'] = $result;
                    }
                    return response()->json($response, 400);
                }

                return back()->with('error', $userMessage);
             }

            // Update subscription with checkout id and create SubscriptionPayment
            try {
                $checkout = $result['checkout_request_id'] ?? null;
                $merchant = $result['merchant_request_id'] ?? null;

                if ($checkout) {
                    $subscription->update(['transaction_id' => $checkout, 'payment_details' => array_merge($subscription->payment_details ?? [], ['merchant_request_id' => $merchant])]);
                }

                SubscriptionPayment::create([
                    'subscription_id' => $subscription->id,
                    'business_id' => $business->id,
                    'checkout_request_id' => $checkout,
                    'merchant_request_id' => $merchant,
                    'mpesa_receipt' => null,
                    'phone' => $request->phone_number,
                    'amount' => $amount,
                    'status' => 'pending',
                    'metadata' => $result['payload'] ?? null,
                    'raw_response' => isset($result['response_body']) ? $result['response_body'] : ($result['response'] ?? null),
                ]);

            } catch (Throwable $e) {
                Log::error('Failed to store subscription payment record', ['error' => $e->getMessage()]);
                // continue — frontend can still poll for status via checkout id
            }

            // Return checkout id for frontend polling
            $dataOut = ['success'=>true,'data'=>['checkout_request_id'=>$result['checkout_request_id'] ?? null,'merchant_request_id'=>$result['merchant_request_id'] ?? null]];
            return $isApiRequest ? response()->json($dataOut) : back()->with('success','M-Pesa prompt sent. We are verifying your payment.');
        }

        // TILL (manual) flow
        if ($request->payment_method === 'till') {
            $sub = Subscription::create([
                'business_id' => $business->id,
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'amount' => $amount,
                'currency' => 'KES',
                'status' => 'pending',
                'transaction_id' => $request->transaction_code,
                'payment_method' => 'MPESA_TILL',
                'payment_details' => [
                    'billing_cycle' => $request->billing_cycle,
                    'is_manual' => true,
                ],
            ]);

            // Record the till transaction in subscription_payments so admin/reconcile can see and act on it
            try {
                SubscriptionPayment::create([
                    'subscription_id' => $sub->id,
                    'business_id' => $business->id,
                    'checkout_request_id' => null,
                    'merchant_request_id' => null,
                    'mpesa_receipt' => null,
                    'phone' => null,
                    'amount' => $amount,
                    'status' => 'pending',
                    'metadata' => ['transaction_code' => $request->transaction_code, 'type' => 'till'],
                    'raw_response' => null,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to create SubscriptionPayment for till transaction', ['error' => $e->getMessage(), 'business_id' => $business->id]);
            }

            // Push SSE event for pending subscription (manual/till)
            try {
                \App\Services\SseService::pushBusinessEvent($business->id, 'subscription.pending', ['plan_id' => $plan->id, 'amount' => $amount]);
            } catch (\Throwable $e) {
                // ignore
            }

            return $isApiRequest ? response()->json(['success'=>true,'message'=>'Transaction logged and awaiting manual verification']) : back()->with('success','Transaction code submitted. Our team will verify and activate your subscription shortly.');
        }

        // CARD payment
        if ($request->payment_method === 'card') {
            $cardData = [
                'card_number' => $request->card_number,
                'expiry_month' => $request->expiry_month,
                'expiry_year' => $request->expiry_year,
                'cvv' => $request->cvv,
                'cardholder_name' => $request->cardholder_name,
                'amount' => $amount,
            ];

            $result = $this->paymentService->processCardPayment($cardData);

            if (!$result['success']) {
                $userMessage = $result['message'] ?? 'Card payment failed';
                return $isApiRequest ? response()->json(['success' => false, 'message' => $userMessage], 400) : back()->with('error', $userMessage);
            }

            $subscription = Subscription::create([
                'business_id' => $business->id,
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'amount' => $amount,
                'currency' => 'KES',
                'status' => 'pending_verification',
                'transaction_id' => $result['transaction_id'],
                'payment_method' => 'CARD',
                'payment_details' => [
                    'billing_cycle' => $request->billing_cycle,
                    'card_number' => '**** **** **** ' . substr($request->card_number, -4),
                    'cardholder' => $request->cardholder_name,
                    'transaction_id' => $result['transaction_id'],
                ],
            ]);

            SubscriptionPayment::create([
                'subscription_id' => $subscription->id,
                'business_id' => $business->id,
                'checkout_request_id' => null,
                'merchant_request_id' => null,
                'mpesa_receipt' => null,
                'phone' => null,
                'amount' => $amount,
                'status' => 'completed',
                'metadata' => [
                    'card_number' => '**** **** **** ' . substr($request->card_number, -4),
                    'cardholder' => $request->cardholder_name,
                    'transaction_id' => $result['transaction_id'],
                ],
                'raw_response' => $result,
            ]);

            return $isApiRequest ? response()->json(['success' => true, 'message' => 'Card payment processed successfully']) : back()->with('success', 'Card payment processed. Subscription is being verified.');
        }

        // BANK TRANSFER
        if ($request->payment_method === 'bank_transfer') {
            $bankData = [
                'reference_number' => $request->reference_number,
                'amount' => $amount,
                'bank_name' => $request->bank_name,
                'account_number' => $request->account_number,
            ];

            $result = $this->paymentService->recordBankTransfer($bankData);

            if (!$result['success']) {
                $userMessage = $result['message'] ?? 'Bank transfer recording failed';
                return $isApiRequest ? response()->json(['success' => false, 'message' => $userMessage], 400) : back()->with('error', $userMessage);
            }

            $subscription = Subscription::create([
                'business_id' => $business->id,
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'amount' => $amount,
                'currency' => 'KES',
                'status' => 'pending_verification',
                'transaction_id' => $request->reference_number,
                'payment_method' => 'BANK_TRANSFER',
                'payment_details' => [
                    'billing_cycle' => $request->billing_cycle,
                    'bank_name' => $request->bank_name,
                    'account_number' => $request->account_number,
                ],
            ]);

            SubscriptionPayment::create([
                'subscription_id' => $subscription->id,
                'business_id' => $business->id,
                'checkout_request_id' => null,
                'merchant_request_id' => null,
                'mpesa_receipt' => null,
                'phone' => null,
                'amount' => $amount,
                'status' => 'completed',
                'metadata' => [
                    'bank_name' => $request->bank_name,
                    'account_number' => $request->account_number,
                ],
                'raw_response' => $result,
            ]);

            return $isApiRequest ? response()->json(['success' => true, 'message' => 'Bank transfer recorded successfully']) : back()->with('success', 'Bank transfer recorded. Subscription is being verified.');
        }

        // CASH payment
        if ($request->payment_method === 'cash') {
            $receivedAmount = $request->received_amount;
            $change = $receivedAmount - $amount;

            if ($change < 0) {
                $userMessage = 'Received amount is less than the payment amount';
                return $isApiRequest ? response()->json(['success' => false, 'message' => $userMessage], 400) : back()->with('error', $userMessage);
            }

            $subscription = Subscription::create([
                'business_id' => $business->id,
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'amount' => $amount,
                'currency' => 'KES',
                'status' => 'pending_verification',
                'transaction_id' => null,
                'payment_method' => 'CASH',
                'payment_details' => [
                    'billing_cycle' => $request->billing_cycle,
                    'received_amount' => $receivedAmount,
                    'change' => $change,
                ],
            ]);

            SubscriptionPayment::create([
                'subscription_id' => $subscription->id,
                'business_id' => $business->id,
                'checkout_request_id' => null,
                'merchant_request_id' => null,
                'mpesa_receipt' => null,
                'phone' => null,
                'amount' => $amount,
                'status' => 'completed',
                'metadata' => [
                    'received_amount' => $receivedAmount,
                    'change' => $change,
                ],
                'raw_response' => null,
            ]);

            return $isApiRequest ? response()->json(['success' => true, 'message' => 'Cash payment recorded successfully', 'change' => $change]) : back()->with('success', 'Cash payment recorded. Subscription is being verified.');
        }

        return $isApiRequest ? response()->json(['success'=>false,'message'=>'Invalid payment method'],400) : back()->with('error','Invalid payment method');
    }
}
