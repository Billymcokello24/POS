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

            return back()->with('success', "Subscription for {$subscription->business->name} approved and activated.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
