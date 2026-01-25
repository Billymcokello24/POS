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

        $plans = Plan::where('is_active', true)->with('features')->get();
        $currentSubscription = $business->activeSubscription;
        $pendingSubscription = $business->subscriptions()->where('status', 'pending')->latest()->first();

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
                ->where('status', 'pending')
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
                $pending->update(['transaction_id' => $mpesaReceipt]);

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

                // Mark subscription as pending_verification (do not auto-activate)
                $pending->update(['status' => 'pending_verification']);

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
                'account_reference' => 'SUBSCRIPTION',
                'transaction_desc' => "Payment for {$plan->name} [{$request->billing_cycle}]",
            ];

            // Use platform-wide MPESA credentials from CMS (superadmin-configured)
            $cmsContent = $this->cmsService->getContent();

            // Normalize CMS content: it may be stored as JSON string, array, or object.
            $cmsArr = [];
            if (is_string($cmsContent)) {
                $decoded = json_decode($cmsContent, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $cmsArr = $decoded;
                }
            } elseif (is_array($cmsContent)) {
                $cmsArr = $cmsContent;
            } elseif (is_object($cmsContent)) {
                $cmsArr = (array) $cmsContent;
            }

            // The MPESA config may be nested under 'mpesa' or it may be the CMS content itself.
            $platformMpesa = $cmsArr['mpesa'] ?? null;
            if (! $platformMpesa) {
                // Heuristic: if the CMS array contains typical mpesa keys, treat it as mpesa config
                $mpesaKeys = ['consumer_key', 'consumer_secret', 'passkey', 'shortcode', 'head_office_passkey'];
                foreach ($mpesaKeys as $k) {
                    if (array_key_exists($k, $cmsArr)) {
                        $platformMpesa = $cmsArr;
                        break;
                    }
                }
            }
             if ($platformMpesa && is_array($platformMpesa)) {
                 // Decrypt any encrypted sensitive values stored by admin (CmsController encrypts on save)
                 try {
                     foreach (['consumer_secret', 'passkey', 'head_office_passkey', 'initiator_password', 'security_credential'] as $skey) {
                         if (array_key_exists($skey, $platformMpesa) && $platformMpesa[$skey]) {
                             try {
                                 $platformMpesa[$skey] = \Illuminate\Support\Facades\Crypt::decryptString($platformMpesa[$skey]);
                             } catch (\Exception $e) {
                                 // If decrypt fails, leave raw value (may already be plaintext)
                             }
                         }
                     }
                 } catch (\Throwable $e) {
                     // If something unexpected happens, fall back to raw values
                 }

                 // Ensure simulate flag boolean
                 $platformMpesa['simulate'] = isset($platformMpesa['simulate']) ? (bool) $platformMpesa['simulate'] : false;
                 $data['mpesa'] = $platformMpesa;
             }

            // Create pending subscription BEFORE initiating STK so we can include subscription id in AccountReference
            $subscription = Subscription::create([
                'business_id' => $business->id,
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'amount' => $amount,
                'currency' => 'KES',
                'status' => 'pending',
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
            if (empty($data['mpesa']) && !empty($businessMpesa)) {
                $data['mpesa'] = $businessMpesa;
            }

            // Initiate STK via PaymentService
            // Basic credential presence check to give clearer errors before remote calls
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
            Subscription::create([
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
