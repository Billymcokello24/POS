<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportService
{
    protected $aiReportService;

    public function __construct(AIReportService $aiReportService)
    {
        $this->aiReportService = $aiReportService;
    }

    /**
     * Generate comprehensive business performance report
     *
     * @param int $businessId
     * @param string $period (today|week|month|year|all)
     * @return array
     */
    public function generateBusinessReport(int $businessId, string $period = 'month'): array
    {
        $business = Business::findOrFail($businessId);

        // Calculate date range
        $dateRange = $this->getDateRange($period);
        $previousDateRange = $this->getPreviousDateRange($period);

        // Fetch all data sections
        $structuredData = [
            'business' => $this->getBusinessInfo($business),
            'reporting_period' => [
                'period' => $period,
                'start_date' => $dateRange['start']->toDateString(),
                'end_date' => $dateRange['end']->toDateString(),
                'generated_at' => now()->toDateTimeString(),
            ],
            'product_performance' => $this->getProductPerformance($businessId, $dateRange),
            'sales_summary' => $this->getSalesSummary($businessId, $dateRange),
            'profit_loss' => $this->getProfitLoss($businessId, $dateRange),
            'kpis' => $this->calculateKPIs($businessId, $dateRange, $previousDateRange),
            'top_products' => $this->getTopProducts($businessId, $dateRange, 10),
            'underperforming_products' => $this->getUnderperformingProducts($businessId, $dateRange, 10),
        ];

        // Send to AI for analysis and insights
        $aiAnalysis = $this->aiReportService->analyzeReport($structuredData);

        // Calculate tile statistics for frontend display
        $tileStats = $this->calculateTileStats($structuredData, $aiAnalysis);

        // Combine data with AI insights
        return array_merge($structuredData, [
            'ai_insights' => $aiAnalysis['insights'],
            'recommendations' => $aiAnalysis['recommendations'],
            'executive_summary' => $aiAnalysis['executive_summary'],
            'tile_stats' => $tileStats, // For frontend tiles
        ]);
    }

    /**
     * Calculate statistics for frontend tiles
     */
    protected function calculateTileStats(array $reportData, array $aiAnalysis): array
    {
        $totalValue = $reportData['profit_loss']['revenue'] ?? 0;
        $totalItems = count($reportData['product_performance'] ?? []);
        $insightsCount = count($aiAnalysis['insights'] ?? []);

        // Calculate performance score (0-100)
        $profitMargin = $reportData['profit_loss']['net_margin_percent'] ?? 0;
        $revenueGrowth = $reportData['kpis']['revenue']['change_percent'] ?? 0;

        // Performance algorithm: weighted average of margin and growth
        $performanceScore = ($profitMargin * 0.6) + ($revenueGrowth * 0.4);

        // Classify performance
        if ($performanceScore >= 20) {
            $performanceLabel = 'Excellent';
            $performanceColor = 'green';
        } elseif ($performanceScore >= 10) {
            $performanceLabel = 'Good';
            $performanceColor = 'green';
        } elseif ($performanceScore >= 0) {
            $performanceLabel = 'Fair';
            $performanceColor = 'yellow';
        } else {
            $performanceLabel = 'Needs Attention';
            $performanceColor = 'red';
        }

        return [
            'total_value' => $totalValue,
            'total_items' => $totalItems,
            'performance' => [
                'score' => round($performanceScore, 1),
                'label' => $performanceLabel,
                'color' => $performanceColor,
            ],
            'insights_count' => $insightsCount,
        ];
    }

    /**
     * Get business information
     */
    protected function getBusinessInfo(Business $business): array
    {
        return [
            'id' => $business->id,
            'name' => $business->name,
            'business_type' => $business->business_type,
            'address' => $business->address,
            'phone' => $business->phone,
            'email' => $business->email,
            'currency' => $business->currency,
        ];
    }

    /**
     * Get date range based on period
     */
    protected function getDateRange(string $period): array
    {
        $end = Carbon::now()->endOfDay();

        switch ($period) {
            case 'today':
                $start = Carbon::today()->startOfDay();
                break;
            case 'week':
                $start = Carbon::now()->startOfWeek();
                break;
            case 'month':
                $start = Carbon::now()->startOfMonth();
                break;
            case 'year':
                $start = Carbon::now()->startOfYear();
                break;
            case 'all':
                $start = Carbon::parse('2000-01-01');
                break;
            default:
                $start = Carbon::now()->startOfMonth();
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Get previous period date range for comparison
     */
    protected function getPreviousDateRange(string $period): array
    {
        $current = $this->getDateRange($period);
        $diff = $current['start']->diffInDays($current['end']);

        return [
            'start' => $current['start']->copy()->subDays($diff + 1),
            'end' => $current['start']->copy()->subDay(),
        ];
    }

    /**
     * Get product performance data
     */
    protected function getProductPerformance(int $businessId, array $dateRange): array
    {
        $performance = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$dateRange['start'], $dateRange['end']])
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'products.sku',
                DB::raw('SUM(sale_items.quantity) as units_sold'),
                DB::raw('SUM(sale_items.total) as revenue'),
                DB::raw('SUM(sale_items.quantity * products.cost_price) as cost'),
                DB::raw('SUM(sale_items.total - (sale_items.quantity * products.cost_price)) as profit'),
                DB::raw('COUNT(DISTINCT sales.id) as transaction_count')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('revenue')
            ->get()
            ->map(function ($item) {
                $revenue = (float) $item->revenue;
                $cost = (float) $item->cost;
                $profit = (float) $item->profit;

                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'sku' => $item->sku,
                    'units_sold' => (int) $item->units_sold,
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'profit' => $profit,
                    'profit_margin' => $cost > 0 ? round(($profit / $revenue) * 100, 2) : 0,
                    'transaction_count' => (int) $item->transaction_count,
                ];
            })
            ->toArray();

        return $performance;
    }

    /**
     * Get sales summary
     */
    protected function getSalesSummary(int $businessId, array $dateRange): array
    {
        $sales = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->select(
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('SUM(total) as total_revenue'),
                DB::raw('SUM(tax_amount) as total_tax'),
                DB::raw('SUM(discount_amount) as total_discounts'),
                DB::raw('AVG(total) as average_transaction_value')
            )
            ->first();

        $totalUnits = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$dateRange['start'], $dateRange['end']])
            ->sum('sale_items.quantity');

        return [
            'total_transactions' => (int) ($sales->total_transactions ?? 0),
            'total_revenue' => (float) ($sales->total_revenue ?? 0),
            'total_tax' => (float) ($sales->total_tax ?? 0),
            'total_discounts' => (float) ($sales->total_discounts ?? 0),
            'average_transaction_value' => (float) ($sales->average_transaction_value ?? 0),
            'total_units_sold' => (int) $totalUnits,
        ];
    }

    /**
     * Get profit and loss statement
     */
    protected function getProfitLoss(int $businessId, array $dateRange): array
    {
        // Revenue
        $revenue = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->sum('total');

        // Cost of Goods Sold (COGS)
        $cogs = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$dateRange['start'], $dateRange['end']])
            ->select(DB::raw('SUM(sale_items.quantity * products.cost_price) as total_cogs'))
            ->value('total_cogs') ?? 0;

        // Gross Profit
        $grossProfit = $revenue - $cogs;
        $grossMargin = $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0;

        // Operating Expenses (if expenses table exists, otherwise 0)
        $expenses = 0;
        if (DB::getSchemaBuilder()->hasTable('expenses')) {
            $expenses = DB::table('expenses')
                ->where('business_id', $businessId)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->sum('amount') ?? 0;
        }

        // Net Profit
        $netProfit = $grossProfit - $expenses;
        $netMargin = $revenue > 0 ? ($netProfit / $revenue) * 100 : 0;

        return [
            'revenue' => (float) $revenue,
            'cogs' => (float) $cogs,
            'gross_profit' => (float) $grossProfit,
            'gross_margin_percent' => round($grossMargin, 2),
            'operating_expenses' => (float) $expenses,
            'net_profit' => (float) $netProfit,
            'net_margin_percent' => round($netMargin, 2),
        ];
    }

    /**
     * Calculate KPIs with period-over-period comparison
     */
    protected function calculateKPIs(int $businessId, array $currentRange, array $previousRange): array
    {
        $currentMetrics = $this->getMetricsForRange($businessId, $currentRange);
        $previousMetrics = $this->getMetricsForRange($businessId, $previousRange);

        return [
            'revenue' => [
                'current' => $currentMetrics['revenue'],
                'previous' => $previousMetrics['revenue'],
                'change_percent' => $this->calculateChangePercent(
                    $currentMetrics['revenue'],
                    $previousMetrics['revenue']
                ),
                'trend' => $this->getTrend($currentMetrics['revenue'], $previousMetrics['revenue']),
            ],
            'transactions' => [
                'current' => $currentMetrics['transactions'],
                'previous' => $previousMetrics['transactions'],
                'change_percent' => $this->calculateChangePercent(
                    $currentMetrics['transactions'],
                    $previousMetrics['transactions']
                ),
                'trend' => $this->getTrend($currentMetrics['transactions'], $previousMetrics['transactions']),
            ],
            'average_order_value' => [
                'current' => $currentMetrics['avg_order_value'],
                'previous' => $previousMetrics['avg_order_value'],
                'change_percent' => $this->calculateChangePercent(
                    $currentMetrics['avg_order_value'],
                    $previousMetrics['avg_order_value']
                ),
                'trend' => $this->getTrend($currentMetrics['avg_order_value'], $previousMetrics['avg_order_value']),
            ],
            'profit_margin' => [
                'current' => $currentMetrics['profit_margin'],
                'previous' => $previousMetrics['profit_margin'],
                'change_percent' => $this->calculateChangePercent(
                    $currentMetrics['profit_margin'],
                    $previousMetrics['profit_margin']
                ),
                'trend' => $this->getTrend($currentMetrics['profit_margin'], $previousMetrics['profit_margin']),
            ],
        ];
    }

    /**
     * Get metrics for a specific date range
     */
    protected function getMetricsForRange(int $businessId, array $dateRange): array
    {
        $sales = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total) as revenue'),
                DB::raw('AVG(total) as avg_value')
            )
            ->first();

        $cogs = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$dateRange['start'], $dateRange['end']])
            ->select(DB::raw('SUM(sale_items.quantity * products.cost_price) as total_cogs'))
            ->value('total_cogs') ?? 0;

        $revenue = (float) ($sales->revenue ?? 0);
        $profit = $revenue - $cogs;
        $profitMargin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

        return [
            'revenue' => $revenue,
            'transactions' => (int) ($sales->count ?? 0),
            'avg_order_value' => (float) ($sales->avg_value ?? 0),
            'profit_margin' => round($profitMargin, 2),
        ];
    }

    /**
     * Get top performing products
     */
    protected function getTopProducts(int $businessId, array $dateRange, int $limit = 10): array
    {
        return DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$dateRange['start'], $dateRange['end']])
            ->select(
                'products.name',
                'products.sku',
                DB::raw('SUM(sale_items.quantity) as units_sold'),
                DB::raw('SUM(sale_items.total) as revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'units_sold' => (int) $item->units_sold,
                    'revenue' => (float) $item->revenue,
                ];
            })
            ->toArray();
    }

    /**
     * Get underperforming products
     */
    protected function getUnderperformingProducts(int $businessId, array $dateRange, int $limit = 10): array
    {
        // Products with sales but lowest revenue
        $lowRevenue = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$dateRange['start'], $dateRange['end']])
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(sale_items.quantity) as units_sold'),
                DB::raw('SUM(sale_items.total) as revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderBy('revenue', 'asc')
            ->limit($limit)
            ->get();

        // Products with no sales in period
        $soldProductIds = $lowRevenue->pluck('id')->toArray();
        $noSales = Product::where('business_id', $businessId)
            ->where('is_active', true)
            ->whereNotIn('id', $soldProductIds)
            ->limit($limit)
            ->get(['id', 'name', 'sku', 'quantity'])
            ->map(function ($product) {
                return [
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'units_sold' => 0,
                    'revenue' => 0,
                    'current_stock' => $product->quantity,
                    'status' => 'no_sales_in_period',
                ];
            });

        return $noSales->take($limit)->toArray();
    }

    /**
     * Calculate percentage change
     */
    protected function calculateChangePercent(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Get trend direction
     */
    protected function getTrend(float $current, float $previous): string
    {
        if ($current > $previous) {
            return 'up';
        } elseif ($current < $previous) {
            return 'down';
        }
        return 'stable';
    }
}
