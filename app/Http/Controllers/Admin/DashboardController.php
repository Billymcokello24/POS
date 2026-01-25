<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Platform-wide stats
        $totalBusinesses = Business::count();
        $activeBusinesses = Business::where('is_active', true)->count();
        $totalSubscribers = Business::whereHas('subscriptions', function ($q) {
            $q->where('status', 'active');
        })->count();

        // Revenue (Sum of all active subscription payments)
        $totalRevenue = Subscription::where('status', 'active')->sum('amount');
        $totalUsers = User::count();

        // Recent Subscriptions
        $recentSubscriptions = Subscription::with('business')
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
            ],
            'recent_subscriptions' => $recentSubscriptions,
        ]);
    }
}
