<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Business;
use App\Models\User;
use App\Models\Role;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;

class PublicSubscriptionController extends Controller
{
    /**
     * Get all active plans for the welcome page
     */
    public function getPlans()
    {
        try {
            $plans = Plan::where('is_active', true)
                ->orderBy('price_monthly', 'asc')
                ->with('features')
                ->get()
                ->map(function ($plan) {
                    return [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'description' => $plan->description,
                        'price_monthly' => $plan->price_monthly,
                        'price_yearly' => $plan->price_yearly,
                        'currency' => 'KES',
                        'features' => $plan->features->map(fn($f) => [
                            'id' => $f->id,
                            'name' => $f->name,
                            'description' => $f->description,
                        ]),
                        'is_popular' => $plan->is_popular ?? false,
                    ];
                });

            return response()->json([
                'success' => true,
                'plans' => $plans,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch plans: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load subscription plans',
            ], 500);
        }
    }

    /**
     * Step 1: Create business and user (pending payment)
     */
    public function createPendingSubscription(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'business_name' => 'required|string|max:255',
            'admin_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $plan = Plan::findOrFail($request->plan_id);
            $amount = $request->billing_cycle === 'yearly'
                ? $plan->price_yearly
                : $plan->price_monthly;

            // Create business and user in a transaction
            $result = DB::transaction(function () use ($request, $plan, $amount) {
                // Create Business (inactive until payment)
                $business = Business::create([
                    'name' => $request->business_name,
                    'email' => $request->email,
                    'currency' => 'KES',
                    'is_active' => false, // Inactive until payment
                ]);

                // Create User
                $user = User::create([
                    'name' => $request->admin_name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'current_business_id' => $business->id,
                    'is_active' => false, // Inactive until payment
                    'role' => 'admin',
                ]);

                // Assign Admin Role
                $adminRole = Role::firstOrCreate(['name' => 'admin'], [
                    'display_name' => 'Administrator',
                    'description' => 'Full access to business settings and data',
                    'level' => 100,
                ]);
                $user->roles()->attach($adminRole->id, ['business_id' => $business->id]);

                // Create Pending Subscription
                $subscription = Subscription::create([
                    'business_id' => $business->id,
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'amount' => $amount,
                    'currency' => 'KES',
                    'status' => 'pending',
                    'payment_method' => 'MPESA_STK',
                    'payment_details' => [
                        'billing_cycle' => $request->billing_cycle,
                        'step' => 'awaiting_payment',
                    ],
                    'is_active' => false,
                    'is_verified' => false,
                ]);

                return [
                    'business' => $business,
                    'user' => $user,
                    'subscription' => $subscription,
                    'amount' => $amount,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Business created. Proceed to payment.',
                'data' => [
                    'subscription_id' => $result['subscription']->id,
                    'business_id' => $result['business']->id,
                    'amount' => $result['amount'],
                    'currency' => 'KES',
                    'plan_name' => $plan->name,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create pending subscription: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription. Please try again.',
            ], 500);
        }
    }

    /**
     * Step 2: Initiate M-PESA STK Push
     */
    public function initiateMpesaPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subscription_id' => 'required|exists:subscriptions,id',
            'phone_number' => 'required|regex:/^254[0-9]{9}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $subscription = Subscription::with('business')->findOrFail($request->subscription_id);

            // Use platform M-PESA credentials (not business credentials)
            // Get platform credentials from CMS or config
            $cmsService = app(\App\Services\CmsService::class);
            $cms = $cmsService->getContent();

            // Extract M-PESA config from CMS
            $cmsArr = [];
            if (is_string($cms)) {
                $decoded = json_decode($cms, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $cmsArr = $decoded;
                }
            } elseif (is_array($cms)) {
                $cmsArr = $cms;
            } elseif (is_object($cms)) {
                $cmsArr = (array) $cms;
            }

            $platformMpesa = $cmsArr['mpesa'] ?? null;

            if (!$platformMpesa || empty($platformMpesa['consumer_key'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'M-PESA is not configured for this system. Please contact support.',
                ], 400);
            }

            // Decrypt sensitive fields
            foreach (['consumer_secret', 'passkey', 'head_office_passkey', 'initiator_password', 'security_credential'] as $skey) {
                if (!empty($platformMpesa[$skey])) {
                    try {
                        $platformMpesa[$skey] = \Illuminate\Support\Facades\Crypt::decryptString($platformMpesa[$skey]);
                    } catch (\Throwable $e) {
                        // Already decrypted or plain text
                    }
                }
            }
            $platformMpesa['simulate'] = isset($platformMpesa['simulate']) ? (bool)$platformMpesa['simulate'] : false;

            // Use PaymentService instead of MpesaService (it handles platform credentials)
            $paymentService = app(\App\Services\PaymentService::class);

            $stkData = [
                'phone_number' => $request->phone_number,
                'amount' => $subscription->amount,
                'account_reference' => "SUB-{$subscription->id}",
                'transaction_desc' => "Subscription: {$subscription->plan_name}",
                'mpesa' => $platformMpesa,
            ];

            $result = $paymentService->initiateMpesaStkPush($stkData);

            // Check if STK push was successful
            if ($result['success']) {
                // Update subscription with checkout request ID
                $subscription->update([
                    'checkout_request_id' => $result['checkout_request_id'] ?? null,
                    'payment_details' => array_merge(
                        $subscription->payment_details ?? [],
                        [
                            'phone_number' => $request->phone_number,
                            'checkout_request_id' => $result['checkout_request_id'] ?? null,
                            'merchant_request_id' => $result['merchant_request_id'] ?? null,
                        ]
                    ),
                ]);

                // Create MpesaPayment record for tracking
                \App\Models\MpesaPayment::create([
                    'business_id' => $subscription->business_id,
                    'subscription_id' => $subscription->id,
                    'checkout_request_id' => $result['checkout_request_id'] ?? null,
                    'merchant_request_id' => $result['merchant_request_id'] ?? null,
                    'phone' => $request->phone_number,
                    'amount' => $subscription->amount,
                    'status' => 'pending',
                    'result_code' => null,
                    'metadata' => [
                        'type' => 'subscription',
                        'plan_id' => $subscription->plan_id,
                        'plan_name' => $subscription->plan_name,
                        'billing_cycle' => $subscription->payment_details['billing_cycle'] ?? 'monthly',
                        'initiated_at' => now()->toDateTimeString(),
                    ],
                ]);

                return response()->json([
                    'success' => true,
                    'message' => $result['customer_message'] ?? 'STK push sent. Please enter your M-PESA PIN.',
                    'data' => [
                        'checkout_request_id' => $result['checkout_request_id'] ?? null,
                        'subscription_id' => $subscription->id,
                    ],
                ]);
            }

            // Handle failed STK push
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to initiate payment',
                'error' => $result['error'] ?? null,
            ], 400);
        } catch (\Exception $e) {
            Log::error('M-PESA STK Push failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Payment initiation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check payment status
     */
    public function checkPaymentStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subscription_id' => 'required|exists:subscriptions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $subscription = Subscription::findOrFail($request->subscription_id);

            return response()->json([
                'success' => true,
                'status' => $subscription->status,
                'is_paid' => $subscription->status === 'active',
                'mpesa_receipt' => $subscription->mpesa_receipt,
            ]);
        } catch (\Exception $e) {
            Log::error('Check payment status failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment status',
            ], 500);
        }
    }
}
