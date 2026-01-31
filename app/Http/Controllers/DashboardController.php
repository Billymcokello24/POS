<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        \Log::debug('Dashboard index reached. User: ' . auth()->user()->email . ' - Is Super: ' . (auth()->user()->is_super_admin ? 'YES' : 'NO'));
        if (auth()->user()->is_super_admin && $request->route()->getName() !== 'admin.dashboard') {
            \Log::debug('Redirecting Super Admin to admin.dashboard');
            return redirect()->route('admin.dashboard');
        }
        $businessId = auth()->user()->current_business_id;
        $user = auth()->user();

        if (!$businessId) {
            $view = $request->route()->getName() === 'admin.dashboard' ? 'Admin/Dashboard' : 'Dashboard';
            return Inertia::render($view, [
                'stats' => [
                    'todaySales' => 0,
                    'totalProducts' => 0,
                    'lowStockItems' => 0,
                    'todayOrders' => 0,
                    'yesterdaySales' => 0,
                    'monthlyGrowth' => 0,
                ],
                'recentSales' => [],
                'lowStockProducts' => [],
            ]);
        }

        // Get today's date range
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $startOfMonth = Carbon::now()->startOfMonth();

        // Sales queries with RBAC filtering and business scoping
        $salesQuery = Sale::where('status', 'completed')->where('business_id', $businessId);

        // RBAC: Cashiers can only see their own sales
        if ($user->isCashier()) {
            $salesQuery->where('cashier_id', $user->id);
        }

        // Today's sales total
        $todaySales = (clone $salesQuery)->whereDate('created_at', $today)->sum('total');

        // Yesterday's sales total
        $yesterdaySales = (clone $salesQuery)->whereDate('created_at', $yesterday)->sum('total');

        // Today's order count
        $todayOrders = (clone $salesQuery)->whereDate('created_at', $today)->count();

        // Monthly growth calculation
        $thisMonthSales = (clone $salesQuery)->whereDate('created_at', '>=', $startOfMonth)->sum('total');

        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $lastMonthSalesQuery = Sale::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->where('status', 'completed')
            ->where('business_id', $businessId);

        // RBAC: Cashiers can only see their own sales for growth calculation
        if ($user->isCashier()) {
            $lastMonthSalesQuery->where('cashier_id', $user->id);
        }

        $lastMonthSales = $lastMonthSalesQuery->sum('total');

        $monthlyGrowth = $lastMonthSales > 0
            ? (($thisMonthSales - $lastMonthSales) / $lastMonthSales) * 100
            : 0;

        // Recent sales (last 10) - apply RBAC filtering and business scoping
        $recentSalesQuery = Sale::with(['customer', 'items'])->where('business_id', $businessId);

        // RBAC: Cashiers can only see their own recent sales
        if ($user->isCashier()) {
            $recentSalesQuery->where('cashier_id', $user->id);
        }

        $recentSales = $recentSalesQuery->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->sale_number,
                    'items' => $sale->items->count(),
                    'time' => $sale->created_at->diffForHumans(),
                    'amount' => (float) $sale->total,
                    'customer' => $sale->customer ? $sale->customer->name : 'Walk-in',
                ];
            });

        // Total active products for this business
        $totalProducts = Product::where('is_active', true)
            ->where('business_id', $businessId)
            ->count();

        // Low stock items count (for this business)
        $lowStockItems = Product::where('track_inventory', true)
            ->where('business_id', $businessId)
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->count();

        // Low stock products (for this business)
        $lowStockProducts = Product::where('track_inventory', true)
            ->where('business_id', $businessId)
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->orderBy('quantity', 'asc')
            ->limit(6)
            ->get()
            ->map(function ($product) {
                return [
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'current' => (int) $product->quantity,
                    'min' => (int) $product->reorder_level,
                ];
            });

        // System Notifications
        $systemNotifications = DB::table('system_notifications')
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();

        $view = $request->route()->getName() === 'admin.dashboard' ? 'Admin/Dashboard' : 'Dashboard';

        return Inertia::render($view, [
            'stats' => [
                'todaySales' => (float) $todaySales,
                'totalProducts' => (int) $totalProducts,
                'lowStockItems' => (int) $lowStockItems,
                'todayOrders' => (int) $todayOrders,
                'yesterdaySales' => (float) $yesterdaySales,
                'monthlyGrowth' => round($monthlyGrowth, 1),
            ],
            'recentSales' => $recentSales,
            'lowStockProducts' => $lowStockProducts,
            'systemNotifications' => $systemNotifications,
        ]);
    }
}

