<?php
namespace App\Services\BI;
use App\Models\Business;
use App\Models\Sale;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
class MetricsEngine
{
    protected int $businessId;
    protected Carbon $startDate;
    protected Carbon $endDate;
    protected Carbon $prevStartDate;
    protected Carbon $prevEndDate;
    public function __construct(int $businessId, string $period = 'month')
    {
        $this->businessId = $businessId;
        $this->setDateRanges($period);
    }
    protected function setDateRanges(string $period): void
    {
        $now = Carbon::now();
        switch ($period) {
            case 'today':
                $this->startDate = $now->copy()->startOfDay();
                $this->endDate = $now->copy()->endOfDay();
                $this->prevStartDate = $now->copy()->subDay()->startOfDay();
                $this->prevEndDate = $now->copy()->subDay()->endOfDay();
                break;
            case 'week':
                $this->startDate = $now->copy()->startOfWeek();
                $this->endDate = $now->copy()->endOfWeek();
                $this->prevStartDate = $now->copy()->subWeek()->startOfWeek();
                $this->prevEndDate = $now->copy()->subWeek()->endOfWeek();
                break;
            case 'month':
                $this->startDate = $now->copy()->startOfMonth();
                $this->endDate = $now->copy()->endOfMonth();
                $this->prevStartDate = $now->copy()->subMonth()->startOfMonth();
                $this->prevEndDate = $now->copy()->subMonth()->endOfMonth();
                break;
            case 'quarter':
                $this->startDate = $now->copy()->startOfQuarter();
                $this->endDate = $now->copy()->endOfQuarter();
                $this->prevStartDate = $now->copy()->subQuarter()->startOfQuarter();
                $this->prevEndDate = $now->copy()->subQuarter()->endOfQuarter();
                break;
            case 'year':
                $this->startDate = $now->copy()->startOfYear();
                $this->endDate = $now->copy()->endOfYear();
                $this->prevStartDate = $now->copy()->subYear()->startOfYear();
                $this->prevEndDate = $now->copy()->subYear()->endOfYear();
                break;
            default:
                $this->startDate = Carbon::create(2020, 1, 1);
                $this->endDate = $now->copy()->endOfDay();
                $this->prevStartDate = $this->startDate->copy();
                $this->prevEndDate = $this->endDate->copy();
        }
    }
    public function getMetrics(): array
    {
        $business = Business::findOrFail($this->businessId);
        $currentKPIs = $this->getCurrentKPIs();
        $previousKPIs = $this->getPreviousKPIs();
        return [
            'business' => $this->getBusinessInfo($business),
            'period' => $this->getPeriodInfo(),
            'current_kpis' => $currentKPIs,
            'previous_kpis' => $previousKPIs,
            'kpi_trends' => $this->calculateKPITrends($currentKPIs, $previousKPIs),
            'historical_kpis' => $this->getHistoricalKPIs(6),
            'product_performance' => $this->getProductPerformance(),
            'top_products' => $this->getTopProducts(10),
            'profit_loss' => $this->getProfitLoss($currentKPIs),
        ];
    }
    protected function getBusinessInfo(Business $business): array
    {
        return [
            'id' => $business->id,
            'name' => $business->name,
            'type' => $business->business_type ?? 'retail',
            'currency' => $business->currency ?? 'KES',
        ];
    }
    protected function getPeriodInfo(): array
    {
        return [
            'start_date' => $this->startDate->toDateString(),
            'end_date' => $this->endDate->toDateString(),
            'generated_at' => now()->toDateTimeString(),
        ];
    }
    protected function getCurrentKPIs(): array
    {
        $sales = Sale::where('business_id', $this->businessId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$this->startDate, $this->endDate]);
        $revenue = (float) $sales->sum('total');
        $transactions = (int) $sales->count();
        $cogs = (float) DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.business_id', $this->businessId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$this->startDate, $this->endDate])
            ->sum(DB::raw('sale_items.quantity * products.cost_price'));
        $grossProfit = $revenue - $cogs;
        $netProfit = $grossProfit;
        $grossMargin = $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0;
        $netMargin = $revenue > 0 ? ($netProfit / $revenue) * 100 : 0;
        $avgOrderValue = $transactions > 0 ? $revenue / $transactions : 0;
        return [
            'revenue' => round($revenue, 2),
            'cogs' => round($cogs, 2),
            'gross_profit' => round($grossProfit, 2),
            'gross_margin' => round($grossMargin, 2),
            'expenses' => 0,
            'net_profit' => round($netProfit, 2),
            'net_margin' => round($netMargin, 2),
            'transactions' => $transactions,
            'avg_order_value' => round($avgOrderValue, 2),
        ];
    }
    protected function getPreviousKPIs(): array
    {
        $sales = Sale::where('business_id', $this->businessId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$this->prevStartDate, $this->prevEndDate]);
        $revenue = (float) $sales->sum('total');
        $transactions = (int) $sales->count();
        $cogs = (float) DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.business_id', $this->businessId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$this->prevStartDate, $this->prevEndDate])
            ->sum(DB::raw('sale_items.quantity * products.cost_price'));
        $grossProfit = $revenue - $cogs;
        $avgOrderValue = $transactions > 0 ? $revenue / $transactions : 0;
        return [
            'revenue' => round($revenue, 2),
            'transactions' => $transactions,
            'gross_profit' => round($grossProfit, 2),
            'avg_order_value' => round($avgOrderValue, 2),
            'net_profit' => round($grossProfit, 2),
            'gross_margin' => $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : 0,
            'net_margin' => $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : 0,
        ];
    }
    protected function calculateKPITrends(array $current, array $previous): array
    {
        $trends = [];
        foreach (['revenue', 'gross_profit', 'net_profit', 'transactions', 'avg_order_value', 'gross_margin', 'net_margin'] as $key) {
            $curr = $current[$key] ?? 0;
            $prev = $previous[$key] ?? 0;
            $change = $prev == 0 ? ($curr > 0 ? 100 : 0) : (($curr - $prev) / abs($prev)) * 100;
            $trends[$key] = [
                'change' => round($change, 2),
                'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable'),
            ];
        }
        return $trends;
    }
    protected function getHistoricalKPIs(int $periods = 6): array
    {
        $historical = [];
        $now = Carbon::now();
        for ($i = $periods - 1; $i >= 0; $i--) {
            $start = $now->copy()->subMonths($i)->startOfMonth();
            $end = $now->copy()->subMonths($i)->endOfMonth();
            $sales = Sale::where('business_id', $this->businessId)
                ->where('status', 'completed')
                ->whereBetween('created_at', [$start, $end]);
            $revenue = (float) $sales->sum('total');
            $transactions = (int) $sales->count();
            $cogs = (float) DB::table('sale_items')
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->join('products', 'sale_items.product_id', '=', 'products.id')
                ->where('sales.business_id', $this->businessId)
                ->where('sales.status', 'completed')
                ->whereBetween('sales.created_at', [$start, $end])
                ->sum(DB::raw('sale_items.quantity * products.cost_price'));
            $historical[] = [
                'period' => $start->format('M Y'),
                'period_short' => $start->format('M'),
                'revenue' => round($revenue, 2),
                'cogs' => round($cogs, 2),
                'gross_profit' => round($revenue - $cogs, 2),
                'transactions' => $transactions,
            ];
        }
        return $historical;
    }
    protected function getProductPerformance(): array
    {
        return DB::table('sale_items')
            ->select([
                'products.id', 'products.name',
                DB::raw('SUM(sale_items.quantity) as units_sold'),
                DB::raw('SUM(sale_items.quantity * sale_items.unit_price) as revenue'),
                DB::raw('SUM(sale_items.quantity * products.cost_price) as cost'),
            ])
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.business_id', $this->businessId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$this->startDate, $this->endDate])
            ->groupBy('products.id', 'products.name')
            ->get()
            ->map(function ($p) {
                $profit = $p->revenue - $p->cost;
                $margin = $p->revenue > 0 ? ($profit / $p->revenue) * 100 : 0;
                return [
                    'name' => $p->name,
                    'units_sold' => (int) $p->units_sold,
                    'revenue' => round($p->revenue, 2),
                    'profit' => round($profit, 2),
                    'margin' => round($margin, 2),
                ];
            })->toArray();
    }
    protected function getTopProducts(int $limit = 10): array
    {
        return collect($this->getProductPerformance())->sortByDesc('revenue')->take($limit)->values()->toArray();
    }
    protected function getProfitLoss(array $kpis): array
    {
        return [
            'revenue' => $kpis['revenue'],
            'cogs' => $kpis['cogs'],
            'gross_profit' => $kpis['gross_profit'],
            'gross_margin_pct' => $kpis['gross_margin'],
            'operating_expenses' => 0,
            'net_profit' => $kpis['net_profit'],
            'net_margin_pct' => $kpis['net_margin'],
        ];
    }
}
