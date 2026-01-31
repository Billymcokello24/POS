<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Payment;
use App\Models\Business;
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
    protected \App\Services\SubscriptionActivationService $activationService;

    public function __construct(PaymentService $paymentService, CmsService $cmsService, \App\Services\SubscriptionActivationService $activationService)
    {
        $this->paymentService = $paymentService;
        $this->cmsService = $cmsService;
        $this->activationService = $activationService;
    }

    /**
     * Display subscription plans and current status
     */
    /**
     * SaaS Auto-Activation Confirmation
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'checkout_request_id' => 'required|string',
        ]);

        $checkout = $request->checkout_request_id;

        // Delegate to centralized activation truth
        $ok = $this->activationService->finalizeFromPayment([
            'checkout_request_id' => $checkout,
        ]);

        if ($ok) {
            return response()->json(['success' => true]);
        }

        // Check if it's actually still pending
        $payment = \App\Models\MpesaPayment::where('checkout_request_id', $checkout)->first();
        if ($payment && $payment->result_code === null) {
            return response()->json(['success' => false, 'message' => 'Payment still pending'], 202);
        }

        return response()->json(['success' => false, 'message' => 'Payment confirmation failed or invalid'], 422);
    }

    public function index()
    {
        $business = Auth::user()->currentBusiness;

        if (! $business) {
            if (Auth::user()->is_super_admin) {
                return redirect()->route('admin.dashboard')->with('error', 'Super Admins manage subscriptions globally.');
            }

            return redirect()->route('dashboard')->with('error', 'No business context found.');
        }

        // Refresh business to ensure latest values
        try {
            $business->refresh();
        } catch (Throwable $_) {
            // ignore refresh errors
        }

        // Proactive reconciliation: attempt to finalize any pending payments
        try {
            $pendingPayments = \App\Models\MpesaPayment::where('business_id', $business->id)
                ->where('status', 'success')
                ->whereNull('subscription_id')
                ->whereNotNull('checkout_request_id')
                ->get();
            
            foreach ($pendingPayments as $p) {
                $this->activationService->finalizeFromPayment(['checkout_request_id' => $p->checkout_request_id]);
            }
        } catch (\Throwable $_) {}

        // Fetch plans
        $plans = Plan::where('is_active', true)->with('features')->orderBy('price_monthly')->get();

        // Get the LATEST active subscription (most recent start)
        $currentSubscription = $business->subscriptions()
            ->with('plan')
            ->whereIn('status', ['active', 'trialing'])
            ->orderBy('starts_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();

        // Get latest "incomplete" subscription candidates
        $candidateStatuses = [Subscription::STATUS_PENDING, 'pending_approval', 'rejected', 'cancelled', 'failed'];
        
        // If an active subscription already exists, hide old rejections/failures
        if ($currentSubscription) {
            $candidateStatuses = [Subscription::STATUS_PENDING, 'pending_approval'];
        }

        $candidates = $business->subscriptions()
            ->whereIn('status', $candidateStatuses)
            ->latest()
            ->first();

        $pendingSubscription = null;
        if ($candidates) {
            $pendingSubscription = $candidates;
            
            // If it's rejected, attach the reason from the payment ledger
            if ($pendingSubscription->status === 'rejected') {
                $rejectedPayment = SubscriptionPayment::where('subscription_id', $pendingSubscription->id)
                    ->where('approval_status', 'rejected')
                    ->latest()
                    ->first();
                
                if ($rejectedPayment) {
                    $pendingSubscription->rejection_reason = $rejectedPayment->rejection_reason;
                }
            }
        }

        // CRITICAL: Ensure business plan_id matches active subscription
        if ($currentSubscription && $business->plan_id !== $currentSubscription->plan_id) {
            Log::warning('Business plan_id mismatch detected!', [
                'business_id' => $business->id,
                'business_plan_id' => $business->plan_id,
                'subscription_plan_id' => $currentSubscription->plan_id,
                'subscription_plan_name' => $currentSubscription->plan->name ?? 'Unknown'
            ]);

            // Update business plan_id to match subscription
            try {
                $business->plan_id = $currentSubscription->plan_id;
                $business->save();
                $business->refresh();
            } catch (\Throwable $_) {
                // ignore save failures here — we'll still render
            }
        }

        // Log for debugging
        Log::info('SubscriptionController@index', [
            'business_id' => $business->id,
            'business_plan_id' => $business->plan_id,
            'current_subscription_id' => $currentSubscription->id ?? null,
            'current_subscription_plan_id' => $currentSubscription->plan_id ?? null,
            'current_subscription_plan_name' => $currentSubscription->plan->name ?? null,
            'pending_subscription_id' => $pendingSubscription->id ?? null,
        ]);

        return Inertia::render('Subscription/Index', [
            'plans' => $plans,
            'currentSubscription' => $currentSubscription ? [
                'id' => $currentSubscription->id,
                'plan_name' => $currentSubscription->plan->name ?? 'Unknown',
                'starts_at' => $currentSubscription->starts_at?->toDateTimeString(),
                'ends_at' => $currentSubscription->ends_at?->toDateTimeString(),
                'status' => $currentSubscription->status,
                'payment_method' => $currentSubscription->payment_method,
            ] : null,
            'pendingSubscription' => $pendingSubscription ? [
                'id' => $pendingSubscription->id,
                'plan_name' => $pendingSubscription->plan->name ?? $pendingSubscription->plan_name ?? 'Unknown',
                'created_at' => $pendingSubscription->created_at?->toDateTimeString(),
                'status' => $pendingSubscription->status,
                'payment_method' => $pendingSubscription->payment_method,
                'transaction_id' => $pendingSubscription->transaction_id ?? $pendingSubscription->checkout_request_id,
                'mpesa_receipt' => $pendingSubscription->mpesa_receipt,
                'failure_reason' => $pendingSubscription->metadata['failure_reason'] ?? null,
                'rejection_reason' => $pendingSubscription->rejection_reason ?? null,
            ] : null,
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'plan_id' => $business->plan_id, // This is now updated
            ],
        ]);
    }

    /**
     * Return subscription status for polling (API)
     */
    public function getSubscriptionStatus(Request $request, Business $business)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // Ensure user has access to this business
        if (! $user->is_super_admin && ! $user->businesses()->where('businesses.id', $business->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Refresh to get latest data
        try { $business->refresh(); } catch (Throwable $_) {}

        // Get the LATEST active subscription
        $activeSubscription = $business->subscriptions()
            ->with('plan')
            ->whereIn('status', ['active', 'trialing'])
            ->orderBy('starts_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();

        // Get latest incomplete subscription for polling
        $candidateStatuses = [Subscription::STATUS_PENDING, 'pending_approval', 'rejected', 'cancelled', 'failed'];
        
        // If an active subscription already exists, hide old rejections/failures
        if ($activeSubscription) {
            $candidateStatuses = [Subscription::STATUS_PENDING, 'pending_approval'];
        }

        $candidates = $business->subscriptions()
            ->with('plan')
            ->whereIn('status', $candidateStatuses)
            ->latest()
            ->first();

        $pendingSubscription = null;
        if ($candidates) {
            $pendingSubscription = $candidates;
            
            if ($pendingSubscription->status === 'rejected') {
                $rejectedPayment = SubscriptionPayment::where('subscription_id', $pendingSubscription->id)
                    ->where('approval_status', 'rejected')
                    ->latest()
                    ->first();
                
                if ($rejectedPayment) {
                    $pendingSubscription->rejection_reason = $rejectedPayment->rejection_reason;
                }
            }
        }

        // ZOMBIE CLEANUP (Point 6 & User Request)
        // If pending subscription is stale (>10m) and has NO payment reference, it's dead. Cancel it.
        // This fixes the "Ref: N/A" issue where a subscription was created but payment init failed/cancelled.
        if ($pendingSubscription && 
            $pendingSubscription->status !== 'rejected' &&
            $pendingSubscription->created_at->lt(now()->subMinutes(10)) &&
            empty($pendingSubscription->transaction_id) && 
            empty($pendingSubscription->checkout_request_id)
        ) {
            Log::info('Auto-Cancelling Zombie Subscription', ['id' => $pendingSubscription->id]);
            $pendingSubscription->update([
                'status' => Subscription::STATUS_CANCELLED,
                'metadata' => array_merge($pendingSubscription->metadata ?? [], ['failure_reason' => 'Auto-Cleanup: Stale (No Payment Ref)'])
            ]);
            // Hide it from the user so they can try again
            $pendingSubscription = null;
        }

        // IMPORTANT: Update business plan_id if it's different from active subscription
        $planUpdated = false;
        if ($activeSubscription && $business->plan_id !== $activeSubscription->plan_id) {
            Log::warning('API: Business plan_id mismatch - updating', [
                'business_id' => $business->id,
                'old_plan_id' => $business->plan_id,
                'new_plan_id' => $activeSubscription->plan_id
            ]);

            try {
                $business->plan_id = $activeSubscription->plan_id;
                $business->save();
                $business->refresh();
                $planUpdated = true;
            } catch (Throwable $_) {
                // ignore
            }
        }

        $response = [
            'success' => true,
            'plan_updated' => $planUpdated,
            'business' => [
                'id' => $business->id,
                'plan_id' => $business->plan_id,
                'features' => $business->active_features ?? [],
                'subscription_updated_at' => $business->subscription_updated_at?->toDateTimeString(),
            ],
            'active' => $activeSubscription ? [
                'id' => $activeSubscription->id,
                'plan_name' => $activeSubscription->plan->name ?? 'Unknown',
                'plan_id' => $activeSubscription->plan_id,
                'starts_at' => $activeSubscription->starts_at?->toDateTimeString(),
                'ends_at' => $activeSubscription->ends_at?->toDateTimeString(),
                'status' => $activeSubscription->status,
                'payment_method' => $activeSubscription->payment_method,
                'verified_at' => $activeSubscription->verified_at?->toDateTimeString(),
                'verified_by' => $activeSubscription->verified_by,
                'payment_confirmed_at' => $activeSubscription->payment_confirmed_at?->toDateTimeString(),
            ] : null,
            'pending' => $pendingSubscription ? [
                'id' => $pendingSubscription->id,
                'plan_name' => $pendingSubscription->plan->name ?? $pendingSubscription->plan_name ?? 'Unknown',
                'created_at' => $pendingSubscription->created_at?->toDateTimeString(),
                'status' => $pendingSubscription->status,
                'payment_method' => $pendingSubscription->payment_method,
                'failure_reason' => $pendingSubscription->metadata['failure_reason'] ?? null,
                'rejection_reason' => $pendingSubscription->rejection_reason ?? null,
            ] : null,
            'business_plan_id' => $business->plan_id, // Return the actual plan_id
        ];

        Log::info('Subscription status API response', [
            'business_id' => $business->id,
            'business_plan_id' => $business->plan_id,
            'active_subscription' => $activeSubscription ? $activeSubscription->plan->name : null,
            'plan_updated' => $planUpdated
        ]);

        return response()->json($response);
    }
}
