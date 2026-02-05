<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Chart Generation Service for PDF Reports
 * Generates chart images using QuickChart.io API
 */
class ChartService
{
    protected $baseUrl = 'https://quickchart.io/chart';

    /**
     * Generate revenue trend line chart
     */
    public function generateRevenueTrendChart(array $data): string
    {
        $config = [
            'type' => 'line',
            'data' => [
                'labels' => $data['labels'] ?? [],
                'datasets' => [[
                    'label' => 'Revenue',
                    'data' => $data['revenue'] ?? [],
                    'borderColor' => 'rgb(75, 192, 192)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'tension' => 0.4,
                ]],
            ],
            'options' => [
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Revenue Trend',
                        'font' => ['size' => 16],
                    ],
                ],
            ],
        ];

        return $this->generateChart($config, 600, 300);
    }

    /**
     * Generate product performance pie chart
     */
    public function generateProductPieChart(array $products): string
    {
        $labels = [];
        $data = [];
        $colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'];

        foreach (array_slice($products, 0, 5) as $index => $product) {
            $labels[] = $product['name'];
            $data[] = $product['revenue'];
        }

        $config = [
            'type' => 'pie',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'data' => $data,
                    'backgroundColor' => $colors,
                ]],
            ],
            'options' => [
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Top Products by Revenue',
                        'font' => ['size' => 16],
                    ],
                    'legend' => [
                        'position' => 'right',
                    ],
                ],
            ],
        ];

        return $this->generateChart($config, 500, 300);
    }

    /**
     * Generate profit margin bar chart
     */
    public function generateProfitMarginChart(array $profitData): string
    {
        $config = [
            'type' => 'bar',
            'data' => [
                'labels' => ['Revenue', 'COGS', 'Gross Profit', 'Expenses', 'Net Profit'],
                'datasets' => [[
                    'label' => 'Amount',
                    'data' => [
                        $profitData['revenue'] ?? 0,
                        $profitData['cogs'] ?? 0,
                        $profitData['gross_profit'] ?? 0,
                        $profitData['operating_expenses'] ?? 0,
                        $profitData['net_profit'] ?? 0,
                    ],
                    'backgroundColor' => [
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                    ],
                ]],
            ],
            'options' => [
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Profit & Loss Breakdown',
                        'font' => ['size' => 16],
                    ],
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                    ],
                ],
            ],
        ];

        return $this->generateChart($config, 600, 300);
    }

    /**
     * Generate KPI comparison chart
     */
    public function generateKPIChart(array $kpis): string
    {
        $config = [
            'type' => 'bar',
            'data' => [
                'labels' => ['Revenue', 'Transactions', 'AOV', 'Margin'],
                'datasets' => [
                    [
                        'label' => 'Change %',
                        'data' => [
                            $kpis['revenue']['change_percent'] ?? 0,
                            $kpis['transactions']['change_percent'] ?? 0,
                            $kpis['average_order_value']['change_percent'] ?? 0,
                            $kpis['profit_margin']['change_percent'] ?? 0,
                        ],
                        'backgroundColor' => function($context) {
                            $value = $context['raw'] ?? 0;
                            return $value >= 0 ? 'rgba(75, 192, 192, 0.8)' : 'rgba(255, 99, 132, 0.8)';
                        },
                    ],
                ],
            ],
            'options' => [
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => 'KPI Performance (% Change)',
                        'font' => ['size' => 16],
                    ],
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                    ],
                ],
            ],
        ];

        return $this->generateChart($config, 600, 300);
    }

    /**
     * Generate chart from config and return base64 image
     */
    protected function generateChart(array $config, int $width = 600, int $height = 300): string
    {
        try {
            $url = $this->baseUrl . '?' . http_build_query([
                'c' => json_encode($config),
                'width' => $width,
                'height' => $height,
                'format' => 'png',
            ]);

            $response = Http::timeout(10)->get($url);

            if ($response->successful()) {
                return 'data:image/png;base64,' . base64_encode($response->body());
            }

            // Fallback: return placeholder
            return $this->generatePlaceholder($width, $height);

        } catch (\Exception $e) {
            return $this->generatePlaceholder($width, $height);
        }
    }

    /**
     * Generate placeholder image if chart generation fails
     */
    protected function generatePlaceholder(int $width, int $height): string
    {
        $svg = <<<SVG
<svg width="{$width}" height="{$height}" xmlns="http://www.w3.org/2000/svg">
    <rect width="100%" height="100%" fill="#f3f4f6"/>
    <text x="50%" y="50%" text-anchor="middle" dominant-baseline="middle" fill="#6b7280" font-size="16">
        Chart not available
    </text>
</svg>
SVG;

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
