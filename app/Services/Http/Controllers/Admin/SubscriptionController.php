<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        // Reconciliation: auto-activate any subscriptions that have successful payments but are not active yet.
        // Only run this for super-admin users to avoid side-effects for regular admins/business users.
        try {
            $user = $request->user();
            if ($user && $user->is_super_admin) {
                // Find subscription payments that are completed and whose subscription is not active.
                // We'll collect candidates from both SubscriptionPayment and MpesaPayment (some records may not be linked)
                $processedCheckouts = [];

                // SubscriptionPayment candidates (including those without subscription_id)
                $subPayments = \App\Models\SubscriptionPayment::whereIn('status', ['completed', 'success'])
                    ->where(function($q) {
                        // include both linked and unlinked payments
                    })
                    ->with('subscription.business')
                    ->get();

                // MpesaPayment candidates (sale-independent)
                $mpesaPayments = \App\Models\MpesaPayment::whereIn('status', ['success', 'completed'])
                    ->whereNotNull('checkout_request_id')
                    ->get();

                $candidates = $subPayments->concat($mpesaPayments);

                foreach ($candidates as $sp) {
                    try {
                        // normalize checkout id
                        $checkout = $sp->checkout_request_id ?? $sp->mpesa_receipt ?? null;
                        if ($checkout && in_array($checkout, $processedCheckouts)) continue;
                        if ($checkout) $processedCheckouts[] = $checkout;

                        // Attempt to resolve the Subscription object using multiple heuristics
                        $subscription = null;

                        // If this is a SubscriptionPayment model with subscription relation
                        if ($sp instanceof \App\Models\SubscriptionPayment && ! empty($sp->subscription_id)) {
                            try { $subscription = \App\Models\Subscription::find($sp->subscription_id); } catch (\Throwable $_) { $subscription = null; }
                        }

                        // If mpesa payment with account_reference SUB-<id>
                        if (! $subscription) {
                            $accountRef = $sp->account_reference ?? ($sp->metadata['account_reference'] ?? null) ?? null;
                            if ($accountRef && preg_match('/^SUB-(\d+)/', $accountRef, $m)) {
                                $sid = (int) $m[1];
                                try { $subscription = \App\Models\Subscription::where('id', $sid)->whereIn('status', ['initiated','pending','pending_verification'])->first(); } catch (\Throwable $_) { $subscription = null; }
                            }
                        }

                        // Try match by transaction_id equal to checkout
                        if (! $subscription && $checkout) {
                            try { $subscription = \App\Models\Subscription::where('transaction_id', $checkout)->whereIn('status', ['initiated','pending','pending_verification'])->first(); } catch (\Throwable $_) { $subscription = null; }
                        }

                        // Try find via SubscriptionPayment mpesa_receipt matching
                        if (! $subscription && $sp instanceof \App\Models\MpesaPayment) {
                            try {
                                $found = \App\Models\SubscriptionPayment::where(function($q) use ($checkout) {
                                    $q->where('checkout_request_id', $checkout)->orWhere('mpesa_receipt', $checkout);
                                })->latest()->first();
                                if ($found && $found->subscription_id) {
                                    $subscription = \App\Models\Subscription::where('id', $found->subscription_id)->whereIn('status', ['initiated','pending','pending_verification'])->first();
                                }
                            } catch (\Throwable $_) { /* ignore */ }
                        }

                        // If still not found, try a heuristic: same business_id + amount + recent pending subscription
                        if (! $subscription && isset($sp->business_id) && isset($sp->amount)) {
                            try {
                                $subscription = \App\Models\Subscription::where('business_id', $sp->business_id)
                                    ->where('amount', $sp->amount)
                                    ->whereIn('status', ['initiated','pending','pending_verification'])
                                    ->orderBy('created_at', 'desc')
                                    ->first();
                            } catch (\Throwable $_) { $subscription = null; }
                        }

                        if (! $subscription) {
                            \Illuminate\Support\Facades\Log::info('Reconcile: unable to resolve subscription for payment candidate', ['checkout' => $checkout, 'candidate' => is_object($sp) ? get_class($sp) : null, 'payload_id' => $sp->id ?? null]);
                            continue;
                        }

                        $sub = $subscription;
                        if (! $sub) continue;

                        // Double-check subscription still not active
                        if ($sub->status === 'active') {
                            \Illuminate\Support\Facades\Log::debug('Reconcile: subscription already active, skipping', ['subscription_id' => $sub->id]);
                            continue;
                        }

                        // Ensure plan exists or plan_name is present
                        if (! method_exists($sub->business, 'activateSubscription')) {
                            \Illuminate\Support\Facades\Log::warning('Reconcile: activateSubscription method not available on business', ['business_id' => $sub->business_id, 'subscription_id' => $sub->id]);
                            continue;
                        }

                        // Try to resolve plan if missing
                        $receipt = $sp->mpesa_receipt ?? $sp->checkout_request_id ?? null;

                        $plan = $sub->plan;
                        if (! $plan && $sub->plan_id) {
                            try { $plan = \App\Models\Plan::find($sub->plan_id); } catch (\Throwable $_) { $plan = null; }
                        }
                        if (! $plan && ! empty($sub->plan_name)) {
                            try { $plan = \App\Models\Plan::where('name', 'like', $sub->plan_name)->first(); } catch (\Throwable $_) { $plan = null; }
                        }

                        if (! $plan) {
                            \Illuminate\Support\Facades\Log::warning('Reconcile: plan not found for subscription; skipping activation', ['subscription_id' => $sub->id, 'plan_id' => $sub->plan_id, 'plan_name' => $sub->plan_name]);
                            // don't attempt activation if plan unknown
                            continue;
                        }

                        // Ensure plan linkage persisted
                        try {
                            if (! $sub->plan_id && $plan->id) {
                                $sub->plan_id = $plan->id;
                                $sub->plan_name = $plan->name;
                                $sub->save();
                            }
                        } catch (\Throwable $_) { /* non-fatal */ }

                        // Attempt activation; log before and after
                        \Illuminate\Support\Facades\Log::info('Reconcile: attempting activation', ['subscription_id' => $sub->id, 'business_id' => $sub->business_id, 'plan_id' => $plan->id, 'receipt' => $receipt]);
                        $sub->business->activateSubscription($sub, $receipt, ['auto_reconcile' => true, 'source' => 'admin_reconcile']);
                        \Illuminate\Support\Facades\Log::info('Reconcile: activation succeeded', ['subscription_id' => $sub->id]);

                        // Mark subscription payment metadata as auto-reconciled
                        try {
                            $sp->metadata = array_merge($sp->metadata ?? [], ['auto_reconcile' => true]);
                            $sp->save();
                        } catch (\Throwable $_) { /* non-fatal */ }

                        // push SSE event to notify clients
                        try { \App\Services\SseService::pushBusinessEvent($sub->business_id, 'subscription.activated', ['id' => $sub->id, 'status' => 'active']); } catch (\Throwable $_) {}

                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error('Admin reconcile activation failed', ['error' => $e->getMessage(), 'subscription_payment_id' => $sp->id, 'trace' => $e->getTraceAsString()]);
                    }
                }
            }
        } catch (\Throwable $_) {
            // swallow reconciliation errors to avoid breaking index load
        }

        $query = Subscription::with(['business', 'plan']);

        // Filtering
        if ($request->filled('search')) {
            $query->whereHas('business', function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%');
            })->orWhere('plan_name', 'like', '%'.$request->search.'%')
                ->orWhere('transaction_id', 'like', '%'.$request->search.'%');
        }

        if ($request->filled('plan')) {
            $query->where('plan_name', $request->plan);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $subscriptions = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        // Attach an 'auto_verified' flag to each subscription item so the frontend can display badge
        try {
            $subscriptions->getCollection()->transform(function ($sub) {
                $auto = false;
                try {
                    $pd = $sub->payment_details ?? [];
                    if (is_array($pd) && !empty($pd['auto_reconcile'])) $auto = true;

                    // Also mark auto if there's any completed SubscriptionPayment for this subscription
                    if (! $auto) {
                        $count = \App\Models\SubscriptionPayment::where('subscription_id', $sub->id)
                            ->whereIn('status', ['completed', 'success'])
                            ->count();
                        if ($count > 0) $auto = true;
                    }
                } catch (\Throwable $_) { }

                $arr = $sub->toArray();
                $arr['auto_verified'] = $auto;
                return (object) $arr;
            });
        } catch (\Throwable $_) { }

        $businesses = Business::select('id', 'name', 'plan_id')->with('plan')->orderBy('name')->get();
        $plans = \App\Models\Plan::all();

        // Revenue Analytics
        $revenue = [
            'today' => Subscription::where('status', 'active')->whereDate('created_at', now())->sum('amount'),
            'this_month' => Subscription::where('status', 'active')->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('amount'),
            'this_year' => Subscription::where('status', 'active')->whereYear('created_at', now()->year)->sum('amount'),
            'total' => Subscription::where('status', 'active')->sum('amount'),
            'monthly_trend' => $this->getMonthlyTrend(),
        ];

        return Inertia::render('Admin/Subscriptions/Index', [
            'subscriptions' => $subscriptions,
            'businesses' => $businesses,
            'plans' => $plans,
            'revenue' => $revenue,
            'filters' => $request->only(['search', 'plan', 'date_from', 'date_to']),
        ]);
    }

    protected function getMonthlyTrend()
    {
        return Subscription::where('status', 'active')
            ->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw('MONTHNAME(created_at) as month, SUM(amount) as total, MONTH(created_at) as month_num')
            ->groupBy('month', 'month_num')
            ->orderBy('month_num')
            ->get();
    }

    public function updatePlan(Request $request, Business $business)
    {
        $request->validate(['plan_id' => 'required|exists:plans,id']);
        $business->update(['plan_id' => $request->plan_id]);

        return back()->with('success', "Business '{$business->name}' moved to NEW plan.");
    }

    public function store(Request $request)
    {
        $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'plan_id' => 'required|exists:plans,id',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
        ]);

        $plan = \App\Models\Plan::findOrFail($request->plan_id);

        Subscription::create([
            'business_id' => $request->business_id,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'status' => 'active',
            'starts_at' => $request->starts_at,
            'ends_at' => $request->ends_at,
        ]);

        return back()->with('success', 'Subscription logged successfully.');
    }

    public function cancel(Subscription $subscription)
    {
        $subscription->update(['status' => 'cancelled']);

        return back()->with('success', 'Subscription cancelled.');
    }

    public function approve(Subscription $subscription)
    {
        try {
            $subscription->business->activateSubscription($subscription, null, [
                'approved_by' => auth()->id(),
                'approval_method' => 'manual',
            ]);

            // Push SSE event to notify business clients that subscription is approved
            try {
                \App\Services\SseService::pushBusinessEvent($subscription->business_id, 'subscription.approved', ['id' => $subscription->id, 'status' => 'active']);
            } catch (\Throwable $e) {
                // ignore
            }

            return back()->with('success', "Subscription for {$subscription->business->name} approved and activated.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reconcile(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->is_super_admin) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $results = [
            'attempted' => 0,
            'activated' => 0,
            'skipped' => 0,
            'errors' => [],
            'messages' => []
        ];

        try {
            // Reuse logic: gather candidates
            $processedCheckouts = [];

            $subPayments = \App\Models\SubscriptionPayment::whereIn('status', ['completed', 'success'])->with('subscription.business')->get();
            $mpesaPayments = \App\Models\MpesaPayment::whereIn('status', ['success', 'completed'])->whereNotNull('checkout_request_id')->get();
            $candidates = $subPayments->concat($mpesaPayments);

            foreach ($candidates as $sp) {
                $results['attempted']++;
                try {
                    $checkout = $sp->checkout_request_id ?? $sp->mpesa_receipt ?? null;
                    if ($checkout && in_array($checkout, $processedCheckouts)) { $results['skipped']++; continue; }
                    if ($checkout) $processedCheckouts[] = $checkout;

                    // Resolve subscription (reuse heuristics)
                    $subscription = null;
                    if ($sp instanceof \App\Models\SubscriptionPayment && ! empty($sp->subscription_id)) {
                        $subscription = \App\Models\Subscription::find($sp->subscription_id);
                    }

                    if (! $subscription) {
                        $accountRef = $sp->account_reference ?? ($sp->metadata['account_reference'] ?? null) ?? null;
                        if ($accountRef && preg_match('/^SUB-(\d+)/', $accountRef, $m)) {
                            $sid = (int) $m[1];
                            $subscription = \App\Models\Subscription::where('id', $sid)->whereIn('status', ['initiated','pending','pending_verification'])->first();
                        }
                    }

                    if (! $subscription && $checkout) {
                        $subscription = \App\Models\Subscription::where('transaction_id', $checkout)->whereIn('status', ['initiated','pending','pending_verification'])->first();
                    }

                    if (! $subscription && $sp instanceof \App\Models\MpesaPayment) {
                        $found = \App\Models\SubscriptionPayment::where(function($q) use ($checkout) { $q->where('checkout_request_id', $checkout)->orWhere('mpesa_receipt', $checkout); })->latest()->first();
                        if ($found && $found->subscription_id) $subscription = \App\Models\Subscription::where('id', $found->subscription_id)->whereIn('status', ['initiated','pending','pending_verification'])->first();
                    }

                    if (! $subscription && isset($sp->business_id) && isset($sp->amount)) {
                        $subscription = \App\Models\Subscription::where('business_id', $sp->business_id)->where('amount', $sp->amount)->whereIn('status', ['initiated','pending','pending_verification'])->orderBy('created_at','desc')->first();
                    }

                    if (! $subscription) {
                        $results['messages'][] = "Could not resolve subscription for checkout={$checkout} id={$sp->id}";
                        continue;
                    }

                    $sub = $subscription;
                    if ($sub->status === 'active') { $results['skipped']++; $results['messages'][] = "Already active: {$sub->id}"; continue; }

                    // resolve plan
                    $plan = $sub->plan;
                    if (! $plan && $sub->plan_id) $plan = \App\Models\Plan::find($sub->plan_id);
                    if (! $plan && ! empty($sub->plan_name)) $plan = \App\Models\Plan::where('name', 'like', $sub->plan_name)->first();
                    if (! $plan) { $results['messages'][] = "Plan missing for subscription {$sub->id}"; continue; }

                    // activate
                    try {
                        $receipt = $sp->mpesa_receipt ?? $sp->checkout_request_id ?? null;
                        $sub->business->activateSubscription($sub, $receipt, ['auto_reconcile' => true, 'source' => 'admin_reconcile']);
                        $results['activated']++;
                        $results['messages'][] = "Activated subscription {$sub->id} (business={$sub->business_id})";

                        try { $sp->metadata = array_merge($sp->metadata ?? [], ['auto_reconcile' => true]); $sp->save(); } catch (\Throwable $_) {}
                    } catch (\Throwable $e) {
                        $results['errors'][] = ['subscription_id' => $sub->id, 'error' => $e->getMessage()];
                    }

                } catch (\Throwable $e) {
                    $results['errors'][] = ['candidate_id' => $sp->id ?? null, 'error' => $e->getMessage()];
                }
            }

            return response()->json(['success' => true, 'results' => $results]);

        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }
}
