<?php

namespace App\Services;

use Illuminate\Support\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf;

class PDFReportService
{
    protected $chartService;

    public function __construct(ChartService $chartService)
    {
        $this->chartService = $chartService;
    }

    /**
     * Generate PDF from report data
     *
     * @param array $reportData
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generatePDF(array $reportData)
    {
        // Generate charts
        $charts = [
            'revenue_trend' => $this->chartService->generateRevenueTrendChart([
                'labels' => ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                'revenue' => $this->estimateWeeklyRevenue($reportData),
            ]),
            'product_pie' => $this->chartService->generateProductPieChart($reportData['top_products'] ?? []),
            'profit_bar' => $this->chartService->generateProfitMarginChart($reportData['profit_loss']),
            'kpi_chart' => $this->chartService->generateKPIChart($reportData['kpis'] ?? []),
        ];

        $reportData['charts'] = $charts;

        $html = $this->generateHTML($reportData);

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);
    }

    /**
     * Estimate weekly revenue distribution (simple approximation)
     */
    protected function estimateWeeklyRevenue(array $reportData): array
    {
        $totalRevenue = $reportData['profit_loss']['revenue'] ?? 0;
        // Simple distribution: split roughly by 4
        return [
            $totalRevenue * 0.22,
            $totalRevenue * 0.25,
            $totalRevenue * 0.28,
            $totalRevenue * 0.25,
        ];
    }

    /**
     * Generate HTML for PDF
     */
    protected function generateHTML(array $reportData): string
    {
        $business = $reportData['business'];
        $period = $reportData['reporting_period'];
        $sales = $reportData['sales_summary'];
        $profitLoss = $reportData['profit_loss'];
        $kpis = $reportData['kpis'];
        $topProducts = $reportData['top_products'];
        $underperforming = $reportData['underperforming_products'];
        $productPerformance = $reportData['product_performance'];
        $executiveSummary = $reportData['executive_summary'] ?? '';
        $insights = $reportData['ai_insights'] ?? [];
        $recommendations = $reportData['recommendations'] ?? [];

        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>AI-Powered Business Intelligence Report - <?= htmlspecialchars($business['name']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #2563eb;
        }

        .header h1 {
            font-size: 20pt;
            color: #1e40af;
            margin-bottom: 5px;
        }

        .header h2 {
            font-size: 14pt;
            color: #64748b;
            font-weight: normal;
        }

        .business-info {
            background: #f8fafc;
            padding: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #2563eb;
        }

        .business-info p {
            margin: 3px 0;
            font-size: 9pt;
        }

        .period-info {
            text-align: right;
            margin-bottom: 15px;
            font-size: 9pt;
            color: #64748b;
        }

        h3 {
            font-size: 12pt;
            color: #1e40af;
            margin: 15px 0 8px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #e2e8f0;
        }

        .executive-summary {
            background: #eff6ff;
            padding: 12px;
            margin: 15px 0;
            border-left: 4px solid #3b82f6;
            font-size: 9.5pt;
            line-height: 1.6;
        }

        .kpi-grid {
            display: table;
            width: 100%;
            margin: 10px 0;
        }

        .kpi-row {
            display: table-row;
        }

        .kpi-card {
            display: table-cell;
            width: 25%;
            padding: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            text-align: center;
        }

        .kpi-label {
            font-size: 8pt;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .kpi-value {
            font-size: 14pt;
            font-weight: bold;
            color: #1e293b;
            margin: 4px 0;
        }

        .kpi-change {
            font-size: 8pt;
            margin-top: 4px;
        }

        .kpi-change.positive {
            color: #059669;
        }

        .kpi-change.negative {
            color: #dc2626;
        }

        .kpi-change.neutral {
            color: #64748b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 9pt;
        }

        table thead {
            background: #1e40af;
            color: white;
        }

        table th {
            padding: 8px 6px;
            text-align: left;
            font-weight: 600;
            font-size: 8.5pt;
        }

        table td {
            padding: 6px 6px;
            border-bottom: 1px solid #e2e8f0;
        }

        table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .insight-box {
            margin: 8px 0;
            padding: 8px;
            border-left: 4px solid #64748b;
            background: #f8fafc;
        }

        .insight-box.positive {
            border-left-color: #059669;
            background: #f0fdf4;
        }

        .insight-box.negative {
            border-left-color: #dc2626;
            background: #fef2f2;
        }

        .insight-title {
            font-weight: bold;
            font-size: 9.5pt;
            margin-bottom: 4px;
            color: #1e293b;
        }

        .insight-description {
            font-size: 8.5pt;
            color: #475569;
            line-height: 1.5;
        }

        .recommendation {
            margin: 8px 0;
            padding: 8px;
            background: #fefce8;
            border-left: 4px solid #eab308;
        }

        .recommendation.high {
            border-left-color: #dc2626;
            background: #fef2f2;
        }

        .recommendation.medium {
            border-left-color: #f59e0b;
            background: #fff7ed;
        }

        .priority-badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            border-radius: 3px;
            margin-right: 5px;
        }

        .priority-high {
            background: #dc2626;
            color: white;
        }

        .priority-medium {
            background: #f59e0b;
            color: white;
        }

        .priority-low {
            background: #64748b;
            color: white;
        }

        .metric-row {
            display: table;
            width: 100%;
            margin: 5px 0;
        }

        .metric-label {
            display: table-cell;
            width: 60%;
            font-weight: 600;
            color: #475569;
        }

        .metric-value {
            display: table-cell;
            width: 40%;
            text-align: right;
            font-weight: bold;
            color: #1e293b;
        }

        .page-break {
            page-break-after: always;
            page-break-inside: avoid;
        }

        /* Print-specific optimization */
        @media print {
            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            img {
                max-width: 100%;
                page-break-inside: avoid;
            }

            .insight-box, .recommendation {
                page-break-inside: avoid;
            }
        }

        /* PDF Page Settings */
        @page {
            size: A4 portrait;
            margin: 15mm;
        }

        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 7pt;
            color: #94a3b8;
            padding: 10px 0;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>AI-Powered Business Intelligence Report</h1>
        <h2><?= htmlspecialchars($business['name']) ?></h2>
        <p style="font-size: 10px; color: #666; margin-top: 5px;">
            ✨ Database metrics analyzed by AI for deeper insights
        </p>
    </div>

    <!-- Business Information -->
    <div class="business-info">
        <p><strong>Business Type:</strong> <?= htmlspecialchars($business['business_type'] ?? 'N/A') ?></p>
        <p><strong>Address:</strong> <?= htmlspecialchars($business['address'] ?? 'N/A') ?></p>
        <p><strong>Contact:</strong> <?= htmlspecialchars($business['phone'] ?? 'N/A') ?> | <?= htmlspecialchars($business['email'] ?? 'N/A') ?></p>
    </div>

    <!-- Period Information -->
    <div class="period-info">
        <p><strong>Reporting Period:</strong> <?= ucfirst($period['period']) ?></p>
        <p><strong>From:</strong> <?= $period['start_date'] ?> <strong>To:</strong> <?= $period['end_date'] ?></p>
        <p><strong>Generated:</strong> <?= $period['generated_at'] ?></p>
    </div>

    <!-- Executive Summary -->
    <?php if ($executiveSummary): ?>
    <h3>Executive Summary</h3>
    <div class="executive-summary">
        <?= nl2br(htmlspecialchars($executiveSummary)) ?>
    </div>
    <?php endif; ?>

    <!-- KPI Dashboard -->
    <h3>Key Performance Indicators</h3>
    <div class="kpi-grid">
        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-label">Revenue</div>
                <div class="kpi-value"><?= $business['currency'] ?> <?= number_format($kpis['revenue']['current'], 2) ?></div>
                <div class="kpi-change <?= $kpis['revenue']['trend'] === 'up' ? 'positive' : ($kpis['revenue']['trend'] === 'down' ? 'negative' : 'neutral') ?>">
                    <?= $kpis['revenue']['change_percent'] > 0 ? '▲' : ($kpis['revenue']['change_percent'] < 0 ? '▼' : '●') ?>
                    <?= abs($kpis['revenue']['change_percent']) ?>%
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Transactions</div>
                <div class="kpi-value"><?= number_format($kpis['transactions']['current']) ?></div>
                <div class="kpi-change <?= $kpis['transactions']['trend'] === 'up' ? 'positive' : ($kpis['transactions']['trend'] === 'down' ? 'negative' : 'neutral') ?>">
                    <?= $kpis['transactions']['change_percent'] > 0 ? '▲' : ($kpis['transactions']['change_percent'] < 0 ? '▼' : '●') ?>
                    <?= abs($kpis['transactions']['change_percent']) ?>%
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Avg Order Value</div>
                <div class="kpi-value"><?= $business['currency'] ?> <?= number_format($kpis['average_order_value']['current'], 2) ?></div>
                <div class="kpi-change <?= $kpis['average_order_value']['trend'] === 'up' ? 'positive' : ($kpis['average_order_value']['trend'] === 'down' ? 'negative' : 'neutral') ?>">
                    <?= $kpis['average_order_value']['change_percent'] > 0 ? '▲' : ($kpis['average_order_value']['change_percent'] < 0 ? '▼' : '●') ?>
                    <?= abs($kpis['average_order_value']['change_percent']) ?>%
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Profit Margin</div>
                <div class="kpi-value"><?= number_format($kpis['profit_margin']['current'], 2) ?>%</div>
                <div class="kpi-change <?= $kpis['profit_margin']['trend'] === 'up' ? 'positive' : ($kpis['profit_margin']['trend'] === 'down' ? 'negative' : 'neutral') ?>">
                    <?= $kpis['profit_margin']['change_percent'] > 0 ? '▲' : ($kpis['profit_margin']['change_percent'] < 0 ? '▼' : '●') ?>
                    <?= abs($kpis['profit_margin']['change_percent']) ?>%
                </div>
            </div>
        </div>
    </div>

    <!-- Profit & Loss Statement -->
    <h3>Profit & Loss Statement</h3>
    <div class="metric-row">
        <div class="metric-label">Total Revenue</div>
        <div class="metric-value"><?= $business['currency'] ?> <?= number_format($profitLoss['revenue'], 2) ?></div>
    </div>
    <div class="metric-row">
        <div class="metric-label">Cost of Goods Sold (COGS)</div>
        <div class="metric-value"><?= $business['currency'] ?> <?= number_format($profitLoss['cogs'], 2) ?></div>
    </div>
    <div class="metric-row" style="border-top: 1px solid #cbd5e1; padding-top: 5px;">
        <div class="metric-label"><strong>Gross Profit</strong></div>
        <div class="metric-value" style="color: #059669;"><strong><?= $business['currency'] ?> <?= number_format($profitLoss['gross_profit'], 2) ?></strong></div>
    </div>
    <div class="metric-row">
        <div class="metric-label">Gross Margin</div>
        <div class="metric-value"><?= number_format($profitLoss['gross_margin_percent'], 2) ?>%</div>
    </div>
    <div class="metric-row" style="margin-top: 10px;">
        <div class="metric-label">Operating Expenses</div>
        <div class="metric-value"><?= $business['currency'] ?> <?= number_format($profitLoss['operating_expenses'], 2) ?></div>
    </div>
    <div class="metric-row" style="border-top: 2px solid #1e40af; padding-top: 5px; margin-top: 5px;">
        <div class="metric-label"><strong>Net Profit</strong></div>
        <div class="metric-value" style="color: <?= $profitLoss['net_profit'] >= 0 ? '#059669' : '#dc2626' ?>;">
            <strong><?= $business['currency'] ?> <?= number_format($profitLoss['net_profit'], 2) ?></strong>
        </div>
    </div>
    <div class="metric-row">
        <div class="metric-label">Net Margin</div>
        <div class="metric-value"><?= number_format($profitLoss['net_margin_percent'], 2) ?>%</div>
    </div>

    <!-- Sales Summary -->
    <h3>Sales Summary</h3>
    <div class="metric-row">
        <div class="metric-label">Total Transactions</div>
        <div class="metric-value"><?= number_format($sales['total_transactions']) ?></div>
    </div>
    <div class="metric-row">
        <div class="metric-label">Total Units Sold</div>
        <div class="metric-value"><?= number_format($sales['total_units_sold']) ?></div>
    </div>
    <div class="metric-row">
        <div class="metric-label">Average Transaction Value</div>
        <div class="metric-value"><?= $business['currency'] ?> <?= number_format($sales['average_transaction_value'], 2) ?></div>
    </div>
    <div class="metric-row">
        <div class="metric-label">Total Discounts Given</div>
        <div class="metric-value"><?= $business['currency'] ?> <?= number_format($sales['total_discounts'], 2) ?></div>
    </div>

    <!-- Page Break -->
    <div class="page-break"></div>

    <!-- Visual Analytics Section -->
    <h2 style="text-align: center; color: #7c3aed; margin: 30px 0 20px 0;">📊 Visual Analytics & Insights</h2>

    <?php if (!empty($reportData['charts'])): ?>

    <!-- Revenue Trend Chart -->
    <div style="margin: 20px 0; text-align: center;">
        <img src="<?= $reportData['charts']['revenue_trend'] ?>" alt="Revenue Trend" style="max-width: 100%; height: auto;" />
    </div>

    <!-- Product Performance Pie Chart -->
    <div style="margin: 20px 0; text-align: center;">
        <img src="<?= $reportData['charts']['product_pie'] ?>" alt="Top Products" style="max-width: 100%; height: auto;" />
    </div>

    <!-- Page Break -->
    <div class="page-break"></div>

    <!-- Profit & Loss Bar Chart -->
    <div style="margin: 20px 0; text-align: center;">
        <img src="<?= $reportData['charts']['profit_bar'] ?>" alt="Profit & Loss" style="max-width: 100%; height: auto;" />
    </div>

    <!-- KPI Performance Chart -->
    <div style="margin: 20px 0; text-align: center;">
        <img src="<?= $reportData['charts']['kpi_chart'] ?>" alt="KPI Performance" style="max-width: 100%; height: auto;" />
    </div>

    <?php endif; ?>

    <!-- Page Break -->
    <div class="page-break"></div>

    <!-- AI Insights -->
    <?php if (!empty($insights)): ?>
    <h3>AI-Generated Insights</h3>
    <?php foreach ($insights as $insight): ?>
    <div class="insight-box <?= $insight['severity'] ?? 'neutral' ?>">
        <div class="insight-title"><?= htmlspecialchars($insight['title'] ?? '') ?></div>
        <div class="insight-description"><?= htmlspecialchars($insight['description'] ?? '') ?></div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- Recommendations -->
    <?php if (!empty($recommendations)): ?>
    <h3>Strategic Recommendations</h3>
    <?php foreach ($recommendations as $rec): ?>
    <div class="recommendation <?= $rec['priority'] ?? 'low' ?>">
        <div class="insight-title">
            <span class="priority-badge priority-<?= $rec['priority'] ?? 'low' ?>"><?= strtoupper($rec['priority'] ?? 'low') ?></span>
            <?= htmlspecialchars($rec['title'] ?? '') ?>
        </div>
        <div class="insight-description">
            <strong>Action:</strong> <?= htmlspecialchars($rec['description'] ?? '') ?><br>
            <strong>Expected Impact:</strong> <?= htmlspecialchars($rec['expected_impact'] ?? '') ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- Top Products -->
    <h3>Top Performing Products</h3>
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>SKU</th>
                <th class="text-right">Units Sold</th>
                <th class="text-right">Revenue</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_slice($topProducts, 0, 15) as $product): ?>
            <tr>
                <td><?= htmlspecialchars($product['name']) ?></td>
                <td><?= htmlspecialchars($product['sku'] ?? 'N/A') ?></td>
                <td class="text-right"><?= number_format($product['units_sold']) ?></td>
                <td class="text-right"><?= $business['currency'] ?> <?= number_format($product['revenue'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Underperforming Products -->
    <?php if (!empty($underperforming)): ?>
    <h3>Products Requiring Attention</h3>
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>SKU</th>
                <th class="text-right">Units Sold</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_slice($underperforming, 0, 10) as $product): ?>
            <tr>
                <td><?= htmlspecialchars($product['name']) ?></td>
                <td><?= htmlspecialchars($product['sku'] ?? 'N/A') ?></td>
                <td class="text-right"><?= number_format($product['units_sold']) ?></td>
                <td><?= htmlspecialchars($product['status'] ?? 'Low sales') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Footer -->
    <div class="footer">
        <p>This report was generated automatically by the AI Business Intelligence System</p>
        <p>© <?= date('Y') ?> <?= htmlspecialchars($business['name']) ?> - Confidential Business Document</p>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}
