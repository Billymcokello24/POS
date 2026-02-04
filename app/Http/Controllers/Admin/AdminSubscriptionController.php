<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\MpesaPayment;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class AdminSubscriptionController extends Controller
{
    protected \App\Services\RevenueService $revenueService;
    protected \App\Services\SubscriptionActivationService $activationService;

    public function __construct(
        \App\Services\RevenueService $revenueService,
        \App\Services\SubscriptionActivationService $activationService
    ) {
        $this->revenueService = $revenueService;
        $this->activationService = $activationService;
    }

    public function index(Request $request)
    {

        // Canonical Ledger: SubscriptionPayment (Snapshot Ledger)
        // User Requirement: "Show successful transactions as in the details of the table"
        $query = \App\Models\SubscriptionPayment::with(['subscription.plan', 'business'])
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', '%' . $search . '%')
                  ->orWhere('mpesa_receipt', 'like', '%' . $search . '%')
                  ->orWhere('checkout_request_id', 'like', '%' . $search . '%')
                  ->orWhere('business_name', 'like', '%' . $search . '%') // Use snapshot
                  ->orWhereHas('business', function ($qb) use ($search) {
                      $qb->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        if ($request->filled('plan')) {
            $plan = $request->plan;
            $query->where(function($q) use ($plan) {
                $q->where('plan_name', $plan) // Use snapshot
                  ->orWhereHas('subscription', function ($subQ) use ($plan) {
                      $subQ->where('plan_name', $plan);
                  });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $payments = $query->paginate(15)->withQueryString();

        // Transform for Vue Frontend
        $payments->getCollection()->transform(function ($payment) {
            return [
                'id' => $payment->id,
                'subscription_id' => $payment->subscription_id,
                'payment_id' => $payment->id,
                // Prioritize Snapshot, fallback to relation
                'plan_name' => $payment->plan_name ?? ($payment->subscription->plan_name ?? 'N/A'),
                'amount' => (float) $payment->amount,
                'currency' => 'KES',
                'status' => strtoupper($payment->status), // 'success', 'failed'
                'subscription_status' => $payment->subscription?->status ?? 'pending', // active, rejected, pending
                'approval_status' => $payment->approval_status ?? 'pending', // pending, approved, rejected
                'billing_cycle' => $payment->billing_cycle ?? 'monthly',
                'mpesa_receipt' => $payment->mpesa_receipt,
                'transaction_id' => $payment->mpesa_receipt ?? $payment->checkout_request_id,
                'payment_method' => 'MPESA_STK',
                // Prioritize Snapshot for dates
                'starts_at' => $payment->plan_start_date ?? ($payment->subscription->starts_at ?? null),
                'ends_at' => $payment->plan_end_date ?? ($payment->subscription->ends_at ?? null),
                'created_at' => $payment->created_at->toDateTimeString(),
                'business' => [
                    'name' => $payment->business_name ?? ($payment->business->name ?? 'Unknown Business')
                ],
                'auto_verified' => ($payment->status === 'success'),
            ];
        });

        // Revenue Intelligence & Health (Driven by RevenueService)
        $revenue = [
            'today' => $this->revenueService->getRevenueToday(),
            'this_month' => $this->revenueService->getRevenueThisMonth(),
            'this_year' => $this->revenueService->getRevenueThisYear(),
            'total' => $this->revenueService->getTotalRevenue(),
            'conversion_rate' => $this->getConversionRate(),
            'monthly_trend' => $this->getMonthlyTrend(),
            'abandoned_checkouts' => MpesaPayment::whereNull('result_code')
                ->where('created_at', '<', now()->subMinutes(15))
                ->where('created_at', '>', now()->subHours(24))
                ->count(),
            'failed_callbacks' => MpesaPayment::whereNotNull('result_code')
                ->where('result_code', '!=', 0)
                ->whereDate('created_at', now())
                ->count(),
        ];

        return Inertia::render('Admin/Subscriptions/Index', [
            'subscriptions' => $payments,
            'businesses' => Business::select('id', 'name')->orderBy('name')->get(),
            'plans' => Plan::all(),
            'revenue' => $revenue,
            'filters' => $request->only(['search', 'plan', 'date_from', 'date_to']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'plan_id' => 'required|exists:plans,id',
            'amount' => 'required|numeric',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date',
        ]);

        $plan = Plan::find($request->plan_id);

        // Record as a Manual MpesaPayment (Truth)
        $payment = MpesaPayment::create([
            'business_id' => $request->business_id,
            'amount' => $request->amount,
            'status' => 'success',
            'result_code' => 0,
            'receipt' => 'MANUAL-' . strtoupper(uniqid()),
            'phone' => 'N/A',
            'metadata' => [
                'type' => 'manual_admin_intervention',
                'admin_id' => auth()->id(),
                'starts_at' => $request->starts_at,
                'ends_at' => $request->ends_at
            ]
        ]);

        // Create the subscription
        $subscription = Subscription::create([
            'business_id' => $request->business_id,
            'plan_id' => $request->plan_id,
            'plan_name' => $plan->name,
            'amount' => $request->amount,
            'status' => 'active',
            'starts_at' => $request->starts_at,
            'ends_at' => $request->ends_at ?? now()->addMonth(),
            'activated_at' => now(),
            'verified_at' => now(),
            'is_active' => true,
            'is_verified' => true,
        ]);

        $payment->update(['subscription_id' => $subscription->id]);

        return back()->with('success', 'Manual transaction recorded and subscription activated.');
    }

    public function approve(\App\Models\SubscriptionPayment $subscriptionPayment)
    {
        try {
            // Allow manual approval of successful OR pending payments (Admin override)
            if (!in_array($subscriptionPayment->status, ['success', 'pending'])) {
                return back()->with('error', 'Cannot approve payments with status: ' . $subscriptionPayment->status);
            }

            // Verify not already approved
            if ($subscriptionPayment->approval_status === 'approved') {
                return back()->with('info', 'Subscription already approved.');
            }

            $subscription = $subscriptionPayment->subscription;
            if (!$subscription) {
                return back()->with('error', 'Subscription not found.');
            }

            $business = $subscription->business;
            if (!$business) {
                return back()->with('error', 'Business not found.');
            }

            // Calculate billing dates based on billing cycle
            $billingCycle = $subscriptionPayment->billing_cycle ?? 'monthly';
            $startDate = now();
            $endDate = $billingCycle === 'yearly'
                ? now()->addYear()
                : now()->addMonth();

            // Update subscription with active status and billing dates
            $subscription->update([
                'status' => 'active',
                'is_active' => true,
                'is_verified' => true,
                'starts_at' => $startDate,
                'ends_at' => $endDate,
                'activated_at' => now(),
            ]);

            // Activate the subscription (sync features, update business)
            $business->activateSubscription($subscription, $subscriptionPayment->mpesa_receipt);

            // Update payment record with approval details and billing dates
            $subscriptionPayment->update([
                'approval_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => auth()->id(),
                'plan_start_date' => $startDate,
                'plan_end_date' => $endDate,
            ]);

            // Log approval
            \App\Models\AuditLog::log(
                'subscription.approved',
                "Subscription for {$business->name} - {$subscription->plan_name} (KES {$subscriptionPayment->amount}) approved by Super Admin. Active from {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}.",
                [
                    'subscription_id' => $subscription->id,
                    'payment_id' => $subscriptionPayment->id,
                    'business_id' => $business->id,
                    'approved_by' => auth()->id(),
                    'billing_cycle' => $billingCycle,
                    'starts_at' => $startDate->toDateTimeString(),
                    'ends_at' => $endDate->toDateTimeString(),
                ]
            );

            // Notify business admin
            $businessAdmin = $business->users()->wherePivot('role_id', function ($q) {
                $q->select('id')->from('roles')->where('name', 'admin');
            })->first() ?: $business->users()->first();

            if ($businessAdmin) {
                $businessAdmin->notify(new \App\Notifications\SubscriptionActivated($subscription));
            }

            // Notify super admin
            auth()->user()->notify(new \App\Notifications\SubscriptionCreatedNotification($subscription));

            return back()->with('success', "Subscription approved and activated for {$business->name}! Active until {$endDate->format('M d, Y')}.");
        } catch (\Exception $e) {
            Log::error('Subscription approval failed', [
                'payment_id' => $subscriptionPayment->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Approval failed: ' . $e->getMessage());
        }
    }

    public function cancel(Subscription $subscription)
    {
        $subscription->update([
            'status' => 'cancelled',
            'is_active' => false
        ]);

        return back()->with('success', 'Subscription cancelled.');
    }

    public function reconcile()
    {
        try {
            // Reconcile pending payments that have checkout_request_id
            $pendingPayments = MpesaPayment::where('status', 'success')
                ->whereNull('subscription_id')
                ->whereNotNull('checkout_request_id')
                ->get();

            $count = 0;
            foreach ($pendingPayments as $p) {
                if ($this->activationService->finalizeFromPayment(['checkout_request_id' => $p->checkout_request_id])) {
                    $count++;
                }
            }

            return response()->json(['success' => true, 'message' => "Reconciliation cycle completed. {$count} subscriptions resolved."]);
        } catch (\Throwable $e) {
            Log::error('Admin reconcile failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    protected function getConversionRate()
    {
        $total = MpesaPayment::count();
        if ($total === 0) return 100;
        $success = MpesaPayment::where('result_code', 0)->count();
        return round(($success / $total) * 100, 1);
    }

    protected function getMonthlyTrend()
    {
        return MpesaPayment::where('result_code', 0)
            ->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw('MONTHNAME(created_at) as month, SUM(amount) as total, MONTH(created_at) as month_num')
            ->groupBy('month', 'month_num')
            ->orderBy('month_num')
            ->get();
    }

    public function destroy(\App\Models\SubscriptionPayment $subscriptionPayment)
    {
        try {
            $paymentInfo = [
                'id' => $subscriptionPayment->id,
                'business_name' => $subscriptionPayment->business_name,
                'plan_name' => $subscriptionPayment->plan_name,
                'amount' => $subscriptionPayment->amount,
                'mpesa_receipt' => $subscriptionPayment->mpesa_receipt,
            ];

            // Log before deletion
            \App\Models\AuditLog::log(
                'subscription_payment.deleted',
                "Subscription payment (ID: {$subscriptionPayment->id}) for {$paymentInfo['business_name']} - {$paymentInfo['plan_name']} (KES {$paymentInfo['amount']}) was deleted by Super Admin.",
                ['payment_id' => $subscriptionPayment->id, 'payment_info' => $paymentInfo]
            );

            // Delete related subscription if exists
            if ($subscriptionPayment->subscription_id) {
                $subscription = Subscription::find($subscriptionPayment->subscription_id);
                if ($subscription) {
                    $subscription->delete();
                }
            }

            // Delete the payment record
            $subscriptionPayment->delete();

            return back()->with('success', 'Subscription payment deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Subscription payment deletion failed', [
                'payment_id' => $subscriptionPayment->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to delete subscription payment: ' . $e->getMessage());
        }
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:subscriptions_payments,id'
        ]);

        try {
            $ids = $request->input('ids');
            $count = 0;

            foreach ($ids as $id) {
                $payment = \App\Models\SubscriptionPayment::find($id);
                if (!$payment) continue;

                $paymentInfo = [
                    'id' => $payment->id,
                    'business_name' => $payment->business_name,
                    'plan_name' => $payment->plan_name,
                    'amount' => $payment->amount,
                ];

                // Log each deletion
                \App\Models\AuditLog::log(
                    'subscription_payment.bulk_deleted',
                    "Subscription payment (ID: {$payment->id}) for {$paymentInfo['business_name']} - {$paymentInfo['plan_name']} was bulk deleted by Super Admin.",
                    ['payment_id' => $payment->id, 'payment_info' => $paymentInfo]
                );

                // Delete related subscription if exists
                if ($payment->subscription_id) {
                    $subscription = Subscription::find($payment->subscription_id);
                    if ($subscription) {
                        $subscription->delete();
                    }
                }

                $payment->delete();
                $count++;
            }

            return back()->with('success', "Successfully deleted {$count} subscription payment(s).");
        } catch (\Exception $e) {
            Log::error('Bulk subscription payment deletion failed', [
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Bulk deletion failed: ' . $e->getMessage());
        }
    }

    public function reject(\App\Models\SubscriptionPayment $subscriptionPayment, Request $request)
    {
        try {
            $request->validate([
                'reason' => 'required|string|max:500'
            ]);

            // Verify not already rejected
            if ($subscriptionPayment->approval_status === 'rejected') {
                return back()->with('info', 'Subscription already rejected.');
            }

            $subscription = $subscriptionPayment->subscription;
            $business = $subscription?->business;

            // Update payment record
            $subscriptionPayment->update([
                'approval_status' => 'rejected',
                'rejection_reason' => $request->reason,
                'approved_by' => auth()->id(), // Track who rejected it
            ]);

            // Update subscription status
            if ($subscription) {
                $subscription->update([
                    'status' => 'rejected',
                    'is_active' => false,
                    'is_verified' => false,
                ]);
            }

            // Log rejection
            \App\Models\AuditLog::log(
                'subscription.rejected',
                "Subscription for {$business?->name} - {$subscription?->plan_name} rejected by Super Admin. Reason: {$request->reason}",
                [
                    'subscription_id' => $subscription?->id,
                    'payment_id' => $subscriptionPayment->id,
                    'business_id' => $business?->id,
                    'rejected_by' => auth()->id(),
                    'reason' => $request->reason
                ]
            );

            return back()->with('success', 'Subscription rejected.');
        } catch (\Exception $e) {
            Log::error('Subscription rejection failed', [
                'payment_id' => $subscriptionPayment->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Rejection failed: ' . $e->getMessage());
        }
    }
}
