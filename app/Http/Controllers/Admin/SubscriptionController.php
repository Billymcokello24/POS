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
    protected $revenueService;
    protected $activationService;

    public function __construct(\App\Services\RevenueService $revenueService, \App\Services\SubscriptionActivationService $activationService)
    {
        $this->revenueService = $revenueService;
        $this->activationService = $activationService;
    }

    public function index(Request $request)
    {
        // Run a lightweight reconciliation for super-admins: try auto-activating pending payments
        try {
            $user = $request->user();
            if ($user && ($user->is_super_admin ?? false)) {
                $this->reconcileCandidates();
            }
        } catch (\Throwable $e) {
            Log::error('SubscriptionController@index reconcile error: ' . $e->getMessage());
        }

        // CRITICAL: Query from subscriptions_payments (The Single Source of Truth)
        // This is the SAME table that /subscription writes to
        $paymentsQuery = SubscriptionPayment::with(['subscription.plan', 'subscription.business', 'business'])
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->filled('search')) {
            $search = $request->search;
            $paymentsQuery->where(function ($q) use ($search) {
                $q->whereHas('business', function ($qb) use ($search) { 
                    $qb->where('name', 'like', '%'.$search.'%'); 
                })
                ->orWhereHas('subscription', function ($qb2) use ($search) { 
                    $qb2->where('plan_name', 'like', '%'.$search.'%')
                        ->orWhere('transaction_id', 'like', '%'.$search.'%'); 
                })
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

        // Transform to the shape the frontend expects
        $payments->getCollection()->transform(function ($pay) {
            $sub = $pay->subscription ?? null;
            $business = $pay->business ?? ($sub ? $sub->business : null);

            $planName = $sub->plan_name ?? ($sub->plan->name ?? null) ?? 'Unknown Plan';
            $amount = (float)($pay->amount ?? ($sub->amount ?? 0));
            $currency = $pay->currency ?? ($sub->currency ?? 'KES');
            
            // Definitively determine status from subscriptions_payments (Financial Truth)
            $status = 'PENDING_PAYMENT';
            if ($pay->status === 'completed' || $pay->status === 'success' || ($sub && $sub->status === 'active')) {
                $status = 'ACTIVE';
            } elseif ($pay->status === 'failed' || $pay->status === 'cancelled') {
                $status = 'FAILED';
            } elseif ($sub && $sub->status === 'cancelled') {
                $status = 'CANCELLED';
            }

            $transactionId = $pay->mpesa_receipt ?? $pay->checkout_request_id ?? ($sub ? $sub->transaction_id : null);
            $autoVerified = ($pay->status === 'completed' || $pay->status === 'success');

            return [
                'id' => $pay->id,
                'subscription_id' => $sub->id ?? null,
                'payment_id' => $pay->id,
                'plan_name' => $planName,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $status,
                'transaction_id' => $transactionId,
                'payment_method' => $pay->payment_method ?? ($sub->payment_method ?? 'MPESA_STK'),
                'starts_at' => $sub->starts_at ?? null,
                'ends_at' => $sub->ends_at ?? null,
                'created_at' => $pay->created_at->toDateTimeString(),
                'business' => ['name' => $business->name ?? 'Unknown'],
                'auto_verified' => $autoVerified,
            ];
        });

        $businesses = Business::select('id', 'name', 'plan_id')->with('plan')->orderBy('name')->get();
        $plans = \App\Models\Plan::all();

        // SaaS Intelligence Metrics (Authoritative Ledger)
        $revenue = [
            'today' => $this->revenueService->getRevenueByPeriod(now()->startOfDay(), now()->endOfDay()),
            'this_month' => $this->revenueService->getRevenueByPeriod(now()->startOfMonth(), now()->endOfMonth()),
            'this_year' => $this->revenueService->getRevenueByPeriod(now()->startOfYear(), now()->endOfYear()),
            'total' => $this->revenueService->getTotalRevenue(),
            'mrr' => $this->revenueService->getMRR(),
            'conversion_rate' => $this->revenueService->getConversionMetrics()['conversion_rate'],
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

    protected function getConversionRate()
    {
        $total = SubscriptionPayment::count();
        if ($total === 0) return 100;
        $success = SubscriptionPayment::whereIn('status', ['completed', 'success'])->count();
        return round(($success / $total) * 100, 1);
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

    /**
     * REMOVED: Manual subscription creation violates payment-as-truth principle.
     * 
     * Admins should NOT create subscriptions manually.
     * All subscriptions must originate from confirmed M-Pesa payments.
     * 
     * If you need to grant a business access:
     * 1. Simulate a payment via the payment system
     * 2. Or create a special "admin grant" payment type
     * 3. But NEVER create a subscription without a payment record
     */
    public function store(Request $request)
    {
        return back()->with('error', 'Manual subscription creation is disabled. All subscriptions must originate from confirmed payments.');
    }

    public function cancel(Subscription $subscription)
    {
        $subscription->update(['status' => 'cancelled']);

        return back()->with('success', 'Subscription cancelled.');
    }

    public function approve(Subscription $subscription)
    {
        try {
            // Centralize activation via SubscriptionPaymentController for consistent ledger sync
            $finalizer = app(\App\Http\Controllers\Api\SubscriptionPaymentController::class);
            $ok = $finalizer->finalizeFromCallback([
                'subscription_id' => $subscription->id,
                'mpesa_receipt' => $subscription->mpesa_receipt ?? $subscription->transaction_id ?? null,
                'checkout_request_id' => $subscription->transaction_id ?? null,
                'amount' => $subscription->amount,
                'source' => 'admin_manual_approve',
                'approved_by' => auth()->id(),
            ]);

            if ($ok) {
                return redirect()->back()->with('success', 'Subscription approved and activated successfully.');
            }
            throw new \Exception("Centralized activation failed.");
        } catch (\Throwable $e) {
            Log::error('Admin approve failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Approval failed: ' . $e->getMessage());
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
        try {
            // Find successful M-Pesa payments with no subscription linked (Gated logic)
            $pendingPayments = \App\Models\MpesaPayment::where('status', \App\Models\MpesaPayment::STATUS_SUCCESS)
                ->where('result_code', 0)
                ->whereNull('subscription_id')
                ->where('created_at', '>=', now()->subDays(7))
                ->get();

            foreach ($pendingPayments as $p) {
                $this->activationService->finalizeFromPayment(['checkout_request_id' => $p->checkout_request_id]);
            }
        } catch (\Throwable $e) {
            Log::error('Admin Reconcile failed', ['error' => $e->getMessage()]);
        }
    }
}
