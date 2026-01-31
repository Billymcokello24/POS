<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use Inertia\Inertia;

class DashboardController extends Controller
{
    protected $revenueService;

    public function __construct(\App\Services\RevenueService $revenueService)
    {
        $this->revenueService = $revenueService;
    }

    public function index()
    {
        // Platform-wide stats (The Truth)
        $totalBusinesses = Business::count();
        $activeBusinesses = Business::whereNotNull('plan_id')
            ->where('plan_id', '!=', 1) // Assuming 1 is Basic/Free
            ->count();
            
        $totalSubscribers = Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->where('is_active', true)
            ->count();

        // Revenue derived from MpesaPayment (Financial Ledger)
        $totalRevenue = $this->revenueService->getTotalRevenue();
        $totalUsers = User::count();
        $mrr = $this->revenueService->getMRR();

        // Recent Subscriptions
        $recentSubscriptions = Subscription::with('business', 'plan')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_businesses' => $totalBusinesses,
                'active_businesses' => $activeBusinesses,
                'total_revenue' => (float) $totalRevenue,
                'total_users' => $totalUsers,
                'total_subscribers' => $totalSubscribers,
                'mrr' => (float) $mrr,
            ],
            'recent_subscriptions' => $recentSubscriptions,
        ]);
    }
}
