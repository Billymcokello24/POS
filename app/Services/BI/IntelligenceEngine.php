<?php
namespace App\Services\BI;
class IntelligenceEngine
{
    public function analyze(array $metrics): array
    {
        return [
            'executive_summary' => $this->generateExecutiveSummary($metrics),
            'insights' => $this->generateInsights($metrics),
            'forecasts' => $this->generateForecasts($metrics),
            'recommendations' => $this->generateRecommendations($metrics),
            'analysis_type' => 'data_driven',
        ];
    }
    protected function generateExecutiveSummary(array $metrics): string
    {
        $current = $metrics['current_kpis'] ?? [];
        $trends = $metrics['kpi_trends'] ?? [];
        $business = $metrics['business'] ?? [];
        $period = $metrics['period'] ?? [];
        $revenue = $current['revenue'] ?? 0;
        $profit = $current['net_profit'] ?? 0;
        $margin = $current['net_margin'] ?? 0;
        $transactions = $current['transactions'] ?? 0;
        $revTrend = $trends['revenue']['change'] ?? 0;
        $currency = $business['currency'] ?? 'KES';
        $startDate = $period['start_date'] ?? 'N/A';
        $endDate = $period['end_date'] ?? 'N/A';
        $performance = $revTrend > 10 ? 'strong' : ($revTrend > 0 ? 'positive' : ($revTrend > -10 ? 'stable' : 'challenging'));
        return sprintf(
            "For the period %s to %s, %s generated %s %s in revenue across %d transactions, achieving a net profit of %s %s (%.1f%% margin). Revenue %s %.1f%% compared to the previous period, indicating %s business performance. %s",
            $startDate,
            $endDate,
            $business['name'] ?? 'The business',
            $currency,
            number_format($revenue, 2),
            $transactions,
            $currency,
            number_format($profit, 2),
            $margin,
            $revTrend >= 0 ? 'increased by' : 'decreased by',
            abs($revTrend),
            $performance,
            $profit > 0 ? 'The business is operating profitably.' : 'Focus on cost optimization is recommended.'
        );
    }
    protected function generateInsights(array $metrics): array
    {
        $insights = [];
        $current = $metrics['current_kpis'] ?? [];
        $trends = $metrics['kpi_trends'] ?? [];
        $topProducts = $metrics['top_products'] ?? [];
        $revTrend = $trends['revenue']['change'] ?? 0;
        if ($revTrend > 15) {
            $insights[] = ['category' => 'Revenue', 'type' => 'positive', 'title' => 'Strong Revenue Growth', 'description' => "Revenue increased by {$revTrend}% compared to the previous period, indicating strong market performance."];
        } elseif ($revTrend < -10) {
            $insights[] = ['category' => 'Revenue', 'type' => 'negative', 'title' => 'Revenue Decline', 'description' => "Revenue decreased by " . abs($revTrend) . "%. Review pricing strategy and marketing efforts."];
        }
        $margin = $current['gross_margin'] ?? 0;
        if ($margin < 20) {
            $insights[] = ['category' => 'Profitability', 'type' => 'warning', 'title' => 'Low Profit Margins', 'description' => "Gross margin at {$margin}% is below optimal levels. Review costs and pricing."];
        } elseif ($margin > 40) {
            $insights[] = ['category' => 'Profitability', 'type' => 'positive', 'title' => 'Healthy Margins', 'description' => "Gross margin at {$margin}% indicates strong pricing power."];
        }
        if (!empty($topProducts)) {
            $top = $topProducts[0];
            $insights[] = ['category' => 'Products', 'type' => 'positive', 'title' => 'Top Performer: ' . ($top['name'] ?? 'Product'), 'description' => sprintf("Leading product with %s revenue and %.1f%% margin.", number_format($top['revenue'] ?? 0), $top['margin'] ?? 0)];
        }
        return $insights ?: [['category' => 'Status', 'type' => 'positive', 'title' => 'Stable Performance', 'description' => 'Business metrics are within normal ranges.']];
    }
    protected function generateForecasts(array $metrics): array
    {
        $current = $metrics['current_kpis'] ?? [];
        $trends = $metrics['kpi_trends'] ?? [];
        $forecasts = [];
        $revTrend = $trends['revenue']['change'] ?? 0;
        $projectedGrowth = max(min($revTrend * 0.8, 20), -15);
        $forecasts[] = [
            'metric' => 'Revenue',
            'projection' => round($current['revenue'] * (1 + $projectedGrowth / 100), 2),
            'confidence' => abs($revTrend) < 10 ? 'high' : 'medium',
            'description' => sprintf("Based on current trends, next period revenue is projected at %s (%.1f%% %s).", number_format($current['revenue'] * (1 + $projectedGrowth / 100), 0), abs($projectedGrowth), $projectedGrowth >= 0 ? 'growth' : 'decline'),
        ];
        return $forecasts;
    }
    protected function generateRecommendations(array $metrics): array
    {
        $recs = [];
        $current = $metrics['current_kpis'] ?? [];
        $trends = $metrics['kpi_trends'] ?? [];
        $margin = $current['gross_margin'] ?? 0;
        if ($margin < 25) {
            $recs[] = ['priority' => 'high', 'title' => 'Improve Profit Margins', 'description' => 'Review supplier contracts and pricing strategy.', 'expected_impact' => '5-15% margin improvement'];
        }
        $revTrend = $trends['revenue']['change'] ?? 0;
        if ($revTrend < 0) {
            $recs[] = ['priority' => 'high', 'title' => 'Reverse Revenue Decline', 'description' => 'Launch promotional campaigns and review market positioning.', 'expected_impact' => 'Stabilize revenue'];
        }
        $recs[] = ['priority' => 'medium', 'title' => 'Focus on Top Performers', 'description' => 'Increase inventory for best-selling products.', 'expected_impact' => '10-20% revenue boost'];
        return $recs;
    }
}
