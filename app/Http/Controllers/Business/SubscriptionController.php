<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\CmsService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Throwable;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    protected $paymentService;

    protected $cmsService;

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
                'payment_method' => 'required|in:stk,till',
                'transaction_code' => 'required_if:payment_method,till|string|min:10',
            ]);
        }

        $business = Auth::user()->currentBusiness;
        if (! $business) {
            return back()->with('error', 'Business context required for payments.');
        }

        $plan = Plan::findOrFail($request->plan_id);
        $amount = $request->billing_cycle === 'monthly' ? $plan->price_monthly : $plan->price_yearly;

        if ($request->payment_method === 'stk') {
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

            // Prepare STK push data
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

            $result = $this->paymentService->initiateMpesaStkPush($data);

            if ($result['success']) {
                $checkoutRequestId = $result['checkout_request_id'] ?? null;

                // update subscription with checkout id and merchant_request
                $subscription->update([
                    'transaction_id' => $checkoutRequestId,
                    'payment_details' => array_merge($subscription->payment_details ?? [], ['merchant_request_id' => $result['merchant_request_id'] ?? null]),
                ]);

                // Create a pending MpesaPayment record so SSE/polling can immediately see it
                try {
                    \App\Models\MpesaPayment::create([
                        'business_id' => $business->id,
                        'sale_id' => null,
                        'checkout_request_id' => $checkoutRequestId,
                        'merchant_request_id' => $result['merchant_request_id'] ?? null,
                        'phone' => $request->phone_number,
                        'amount' => $amount,
                        'reference' => null,
                        'account_reference' => $accountRef,
                        'status' => 'pending',
                        'raw_response' => $result,
                        'metadata' => ['used_mapping' => $result['used_mapping'] ?? null],
                    ]);

                    // Record a subscription payment placeholder in subscription_payments table
                    try {
                        \App\Models\SubscriptionPayment::create([
                            'subscription_id' => $subscription->id ?? null,
                            'business_id' => $business->id,
                            'checkout_request_id' => $checkoutRequestId,
                            'merchant_request_id' => $result['merchant_request_id'] ?? null,
                            'mpesa_receipt' => null,
                            'phone' => $request->phone_number,
                            'amount' => $amount,
                            'status' => 'pending',
                            'metadata' => ['account_reference' => $accountRef, 'used_mapping' => $result['used_mapping'] ?? null],
                            'raw_response' => $result,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to create SubscriptionPayment (pending) for STK', ['error' => $e->getMessage()]);
                    }
                } catch (Throwable $e) {
                    Log::warning('Failed to create initial MpesaPayment for subscription', ['error' => $e->getMessage()]);
                }

                // We intentionally do NOT create a duplicate entry in `payments` for subscription STK flows.
                // Subscription-related transactions are recorded in `subscription_payments` (and mpesa_payments).
                Log::info('Subscription STK: recorded subscription_payments/mpesa_payments; skipping payments table entry', ['subscription_id' => $subscription->id ?? null, 'checkout' => $checkoutRequestId]);

                // Generate short-lived SSE token so EventSource can connect without cookies
                $sseToken = null;
                if ($checkoutRequestId) {
                    try {
                        $sseToken = bin2hex(random_bytes(16));
                        Cache::put('mpesa_sse_' . $checkoutRequestId, $sseToken, now()->addMinutes(6));
                    } catch (\Exception $e) {
                        Log::warning('Failed to generate SSE token for mpesa', ['error' => $e->getMessage()]);
                        $sseToken = null;
                    }
                }

                if ($isApiRequest) {
                    return response()->json([
                        'success' => true,
                        'message' => $result['customer_message'] ?? 'STK Push sent. Please check your phone to complete payment',
                        'data' => [
                            'checkout_request_id' => $checkoutRequestId,
                            'merchant_request_id' => $result['merchant_request_id'] ?? null,
                            'used_mapping' => $result['used_mapping'] ?? null,
                            'sse_token' => $sseToken,
                        ],
                    ], 200);
                }

                return back()->with('success', 'STK Push sent. Please enter your M-Pesa PIN on your phone.');
            } else {
                // STK failed; keep subscription pending but log error
                Log::error('Subscription STK initiation failed', ['result' => $result]);

                // Provide more details in debug mode for local troubleshooting
                $userMessage = $result['message'] ?? 'Failed to initiate M-Pesa payment.';
                $debugPayload = null;
                if (config('app.debug')) {
                    $debugPayload = $result['error'] ?? $result;
                }

                if ($isApiRequest) {
                    $payload = ['success' => false, 'message' => $userMessage];
                    if ($debugPayload !== null) $payload['error'] = $debugPayload;
                    return response()->json($payload, 400);
                }

                return back()->with('error', $userMessage);
            }
        } else {
            // M-Pesa Till (Manual Verification)
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

            return back()->with('success', 'Transaction code submitted. Our team will verify and activate your subscription shortly.');
        }
    }
}
