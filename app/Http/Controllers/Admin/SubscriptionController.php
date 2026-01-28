<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        // Run a lightweight reconciliation for super-admins: try auto-activating pending subscriptions
        try {
            $user = $request->user();
            if ($user && ($user->is_super_admin ?? false)) {
                $this->reconcileCandidates();
            }
        } catch (\Throwable $e) {
            // swallow errors so page still loads
            Log::error('SubscriptionController@index reconcile error: ' . $e->getMessage());
        }

        // Build admin ledger from authoritative SubscriptionPayment table
        $paymentsQuery = SubscriptionPayment::with(['subscription.plan', 'subscription.business', 'business'])
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->filled('search')) {
            $search = $request->search;
            $paymentsQuery->where(function ($q) use ($search) {
                $q->whereHas('business', function ($qb) use ($search) { $qb->where('name', 'like', '%'.$search.'%'); })
                  ->orWhereHas('subscription', function ($qb2) use ($search) { $qb2->where('plan_name', 'like', '%'.$search.'%')->orWhere('transaction_id', 'like', '%'.$search.'%'); })
                  ->orWhere('checkout_request_id', 'like', '%'.$search.'%')
                  ->orWhere('mpesa_receipt', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('plan')) {
            $plan = $request->plan;
            $paymentsQuery->whereHas('subscription', function ($q) use ($plan) {
                $q->where('plan_name', $plan);
            });
        }

        if ($request->filled('date_from')) {
            $paymentsQuery->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $paymentsQuery->whereDate('created_at', '<=', $request->date_to);
        }

        $payments = $paymentsQuery->paginate(15)->withQueryString();

        // Transform to the shape the frontend expects (use arrays for stability)
        $payments->getCollection()->transform(function ($pay) {
            $sub = $pay->subscription ?? null;
            $business = $sub->business ?? $pay->business ?? null;

            $planName = $sub->plan_name ?? ($sub->plan->name ?? null) ?? '';
            $amount = $pay->amount ?? ($sub->amount ?? 0);
            $currency = $sub->currency ?? ($business->currency ?? 'KES');
            $status = $sub->status ?? ($pay->status ?? 'pending');
            $transactionId = $sub->transaction_id ?? $pay->mpesa_receipt ?? $pay->checkout_request_id ?? null;

            return [
                'id' => $sub->id ?? null,
                'subscription_id' => $sub->id ?? null,
                'payment_id' => $pay->id ?? null,
                'plan_name' => $planName,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $status,
                'transaction_id' => $transactionId,
                'payment_method' => $sub->payment_method ?? 'MPESA_STK',
                'payment_details' => $sub->payment_details ?? ['phone' => $pay->phone ?? null],
                'starts_at' => $sub->starts_at ?? null,
                'ends_at' => $sub->ends_at ?? null,
                'created_at' => $sub->created_at ?? $pay->created_at,
                'business' => ['name' => $business->name ?? 'Unknown'],
                'mpesa_receipt' => $pay->mpesa_receipt ?? $sub->mpesa_receipt ?? null,
                'auto_verified' => ($pay->status === 'completed') || ($sub && $sub->status === 'active'),
                'raw_payment' => $pay,
            ];
        });

        $businesses = Business::select('id', 'name', 'plan_id')->with('plan')->orderBy('name')->get();
        $plans = \App\Models\Plan::all();

        $revenue = [
            'today' => Subscription::where('status', 'active')->whereDate('created_at', now())->sum('amount'),
            'this_month' => Subscription::where('status', 'active')->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('amount'),
            'this_year' => Subscription::where('status', 'active')->whereYear('created_at', now()->year)->sum('amount'),
            'total' => Subscription::where('status', 'active')->sum('amount'),
            'monthly_trend' => $this->getMonthlyTrend(),
        ];

        return Inertia::render('Admin/Subscriptions/Index', [
            'subscriptions' => $payments,
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

        $subscription = Subscription::create([
            'business_id' => $request->business_id,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'status' => 'active',
            'starts_at' => $request->starts_at,
            'ends_at' => $request->ends_at,
        ]);

        // Ensure a ledger row exists
        try {
            SubscriptionPayment::create([
                'subscription_id' => $subscription->id,
                'business_id' => $subscription->business_id,
                'checkout_request_id' => $subscription->transaction_id ?? null,
                'merchant_request_id' => null,
                'mpesa_receipt' => $subscription->mpesa_receipt ?? null,
                'phone' => null,
                'amount' => $subscription->amount,
                'status' => 'completed',
                'metadata' => ['created_by_admin' => auth()->id(), 'note' => 'manually created by admin'],
                'raw_response' => null,
            ]);
        } catch (\Throwable $_) {
            Log::warning('Admin store: failed to persist subscription_payment', ['subscription_id' => $subscription->id]);
        }

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

            // Update/create ledger row
            try {
                $sp = SubscriptionPayment::where('subscription_id', $subscription->id)
                    ->orWhere('checkout_request_id', $subscription->transaction_id)
                    ->orWhere('mpesa_receipt', $subscription->mpesa_receipt)
                    ->latest()
                    ->first();

                if ($sp) {
                    $sp->status = 'completed';
                    $sp->mpesa_receipt = $sp->mpesa_receipt ?? $subscription->mpesa_receipt ?? $subscription->transaction_id;
                    $sp->metadata = array_merge($sp->metadata ?? [], ['approved_by' => auth()->id(), 'approval_method' => 'manual', 'auto_reconcile' => true]);
                    $sp->save();
                } else {
                    SubscriptionPayment::create([
                        'subscription_id' => $subscription->id,
                        'business_id' => $subscription->business_id,
                        'checkout_request_id' => $subscription->transaction_id ?? null,
                        'merchant_request_id' => null,
                        'mpesa_receipt' => $subscription->mpesa_receipt ?? $subscription->transaction_id ?? null,
                        'phone' => null,
                        'amount' => $subscription->amount,
                        'status' => 'completed',
                        'metadata' => ['approved_by' => auth()->id(), 'approval_method' => 'manual', 'auto_reconcile' => true],
                        'raw_response' => null,
                    ]);
                }
            } catch (\Throwable $_) {
                Log::warning('Admin approve: failed to update/create subscription_payment', ['subscription_id' => $subscription->id]);
            }

            try { \App\Services\SseService::pushBusinessEvent($subscription->business_id, 'subscription.approved', ['id' => $subscription->id, 'status' => 'active']); } catch (\Throwable $_) {}

            return back()->with('success', "Subscription for {$subscription->business->name} approved and activated.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reconcile(Request $request)
    {
        $user = $request->user();
        if (! $user || ! ($user->is_super_admin ?? false)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Reuse the reconciliation helper
        try {
            $this->reconcileCandidates();
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }

    /**
     * Gather candidates from SubscriptionPayment and MpesaPayment and attempt activation.
     */
    protected function reconcileCandidates()
    {
        $processed = [];

        $subPayments = SubscriptionPayment::whereIn('status', ['completed', 'success'])->with('subscription.business')->get();
        $mpesaPayments = \App\Models\MpesaPayment::whereIn('status', ['success', 'completed'])->whereNotNull('checkout_request_id')->get();

        $candidates = $subPayments->concat($mpesaPayments);

        foreach ($candidates as $c) {
            try {
                $checkout = $c->checkout_request_id ?? $c->mpesa_receipt ?? null;
                if ($checkout && in_array($checkout, $processed)) continue;
                if ($checkout) $processed[] = $checkout;

                // Resolve subscription
                $subscription = null;
                if ($c instanceof SubscriptionPayment && ! empty($c->subscription_id)) {
                    $subscription = Subscription::find($c->subscription_id);
                }

                if (! $subscription) {
                    $accountRef = $c->account_reference ?? ($c->metadata['account_reference'] ?? null) ?? null;
                    if ($accountRef && preg_match('/^SUB-(\d+)/', $accountRef, $m)) {
                        $sid = (int) $m[1];
                        $subscription = Subscription::where('id', $sid)->whereIn('status', ['initiated','pending','pending_verification'])->first();
                    }
                }

                if (! $subscription && $checkout) {
                    $subscription = Subscription::where('transaction_id', $checkout)->whereIn('status', ['initiated','pending','pending_verification'])->first();
                }

                if (! $subscription && isset($c->business_id) && isset($c->amount)) {
                    $subscription = Subscription::where('business_id', $c->business_id)->where('amount', $c->amount)->whereIn('status', ['initiated','pending','pending_verification'])->orderBy('created_at','desc')->first();
                }

                if (! $subscription) {
                    Log::info('Reconcile: could not resolve subscription for candidate', ['checkout' => $checkout, 'candidate_id' => $c->id ?? null]);
                    continue;
                }

                if ($subscription->status === 'active') continue;

                // Resolve plan
                $plan = $subscription->plan;
                if (! $plan && $subscription->plan_id) $plan = \App\Models\Plan::find($subscription->plan_id);
                if (! $plan && ! empty($subscription->plan_name)) $plan = \App\Models\Plan::where('name', 'like', $subscription->plan_name)->first();
                if (! $plan) { Log::warning('Reconcile: plan missing for subscription', ['subscription_id' => $subscription->id]); continue; }

                $receipt = $c->mpesa_receipt ?? $c->checkout_request_id ?? null;

                // Activate
                if (method_exists($subscription->business, 'activateSubscription')) {
                    $subscription->business->activateSubscription($subscription, $receipt, ['auto_reconcile' => true, 'source' => 'admin_reconcile']);
                    try { $c->metadata = array_merge($c->metadata ?? [], ['auto_reconcile' => true]); $c->save(); } catch (\Throwable $_) {}
                    try { \App\Services\SseService::pushBusinessEvent($subscription->business_id, 'subscription.activated', ['id' => $subscription->id, 'status' => 'active']); } catch (\Throwable $_) {}
                }

            } catch (\Throwable $e) {
                Log::error('Reconcile candidate failed: ' . $e->getMessage());
            }
        }
    }
}
