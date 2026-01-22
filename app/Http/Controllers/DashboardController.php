<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $businessId = auth()->user()->current_business_id;

        if (!$businessId) {
            return Inertia::render('Dashboard', [
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

        // Today's sales total
        $todaySales = Sale::where('business_id', $businessId)
            ->whereDate('created_at', $today)
            ->where('status', 'completed')
            ->sum('total');

        // Yesterday's sales total
        $yesterdaySales = Sale::where('business_id', $businessId)
            ->whereDate('created_at', $yesterday)
            ->where('status', 'completed')
            ->sum('total');

        // Today's order count
        $todayOrders = Sale::where('business_id', $businessId)
            ->whereDate('created_at', $today)
            ->count();

        // Total active products
        $totalProducts = Product::where('business_id', $businessId)
            ->where('is_active', true)
            ->count();

        // Low stock items count
        $lowStockItems = Product::where('business_id', $businessId)
            ->where('track_inventory', true)
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->count();

        // Monthly growth calculation
        $thisMonthSales = Sale::where('business_id', $businessId)
            ->whereDate('created_at', '>=', $startOfMonth)
            ->where('status', 'completed')
            ->sum('total');

        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $lastMonthSales = Sale::where('business_id', $businessId)
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->where('status', 'completed')
            ->sum('total');

        $monthlyGrowth = $lastMonthSales > 0
            ? (($thisMonthSales - $lastMonthSales) / $lastMonthSales) * 100
            : 0;

        // Recent sales (last 10)
        $recentSales = Sale::with(['customer', 'items'])
            ->where('business_id', $businessId)
            ->orderBy('created_at', 'desc')
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

        // Low stock products
        $lowStockProducts = Product::where('business_id', $businessId)
            ->where('track_inventory', true)
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

        return Inertia::render('Dashboard', [
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
        ]);
    }
}

