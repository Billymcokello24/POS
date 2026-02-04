<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

class ExcelReportService
{
    /**
     * Generate Excel file from report data
     *
     * @param array $reportData
     * @return Spreadsheet
     */
    public function generateExcel(array $reportData): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        // Summary Sheet
        $this->createSummarySheet($spreadsheet, $reportData);

        // Product Performance Sheet
        $this->createProductPerformanceSheet($spreadsheet, $reportData);

        // Insights & Recommendations Sheet
        $this->createInsightsSheet($spreadsheet, $reportData);

        // Set active sheet to summary
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Create summary sheet
     */
    protected function createSummarySheet(Spreadsheet $spreadsheet, array $reportData): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Executive Summary');

        $business = $reportData['business'];
        $period = $reportData['reporting_period'];
        $sales = $reportData['sales_summary'];
        $profitLoss = $reportData['profit_loss'];
        $kpis = $reportData['kpis'];

        $row = 1;

        // Header
        $sheet->setCellValue("A{$row}", 'AI-POWERED BUSINESS INTELLIGENCE REPORT');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(16);
        $sheet->mergeCells("A{$row}:D{$row}");
        $row++;

        $sheet->setCellValue("A{$row}", '✨ Database metrics analyzed by AI for deeper insights');
        $sheet->getStyle("A{$row}")->getFont()->setSize(9)->setItalic(true);
        $sheet->getStyle("A{$row}")->getFont()->getColor()->setRGB('666666');
        $sheet->mergeCells("A{$row}:D{$row}");
        $row += 2;

        // Business Info
        $sheet->setCellValue("A{$row}", 'Business Name:');
        $sheet->setCellValue("B{$row}", $business['name']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue("A{$row}", 'Business Type:');
        $sheet->setCellValue("B{$row}", $business['business_type'] ?? 'N/A');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue("A{$row}", 'Reporting Period:');
        $sheet->setCellValue("B{$row}", ucfirst($period['period']));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue("A{$row}", 'Date Range:');
        $sheet->setCellValue("B{$row}", "{$period['start_date']} to {$period['end_date']}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue("A{$row}", 'Generated:');
        $sheet->setCellValue("B{$row}", $period['generated_at']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row += 2;

        // Executive Summary
        if (!empty($reportData['executive_summary'])) {
            $sheet->setCellValue("A{$row}", 'EXECUTIVE SUMMARY');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
            $this->applyHeaderStyle($sheet, "A{$row}:D{$row}");
            $row++;

            $sheet->setCellValue("A{$row}", $reportData['executive_summary']);
            $sheet->getStyle("A{$row}")->getAlignment()->setWrapText(true);
            $sheet->mergeCells("A{$row}:D{$row}");
            $row += 3;
        }

        // KPIs
        $sheet->setCellValue("A{$row}", 'KEY PERFORMANCE INDICATORS');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $this->applyHeaderStyle($sheet, "A{$row}:E{$row}");
        $row++;

        // KPI Headers
        $sheet->setCellValue("A{$row}", 'Metric');
        $sheet->setCellValue("B{$row}", 'Current');
        $sheet->setCellValue("C{$row}", 'Previous');
        $sheet->setCellValue("D{$row}", 'Change %');
        $sheet->setCellValue("E{$row}", 'Trend');
        $this->applyTableHeaderStyle($sheet, $row);
        $row++;

        // Revenue KPI
        $sheet->setCellValue("A{$row}", 'Revenue');
        $sheet->setCellValue("B{$row}", $kpis['revenue']['current']);
        $sheet->setCellValue("C{$row}", $kpis['revenue']['previous']);
        $sheet->setCellValue("D{$row}", $kpis['revenue']['change_percent']);
        $sheet->setCellValue("E{$row}", strtoupper($kpis['revenue']['trend']));
        $this->applyTrendColor($sheet, "E{$row}", $kpis['revenue']['trend']);
        $this->applyNumberFormat($sheet, "B{$row}:C{$row}", $business['currency']);
        $row++;

        // Transactions KPI
        $sheet->setCellValue("A{$row}", 'Transactions');
        $sheet->setCellValue("B{$row}", $kpis['transactions']['current']);
        $sheet->setCellValue("C{$row}", $kpis['transactions']['previous']);
        $sheet->setCellValue("D{$row}", $kpis['transactions']['change_percent']);
        $sheet->setCellValue("E{$row}", strtoupper($kpis['transactions']['trend']));
        $this->applyTrendColor($sheet, "E{$row}", $kpis['transactions']['trend']);
        $row++;

        // AOV KPI
        $sheet->setCellValue("A{$row}", 'Average Order Value');
        $sheet->setCellValue("B{$row}", $kpis['average_order_value']['current']);
        $sheet->setCellValue("C{$row}", $kpis['average_order_value']['previous']);
        $sheet->setCellValue("D{$row}", $kpis['average_order_value']['change_percent']);
        $sheet->setCellValue("E{$row}", strtoupper($kpis['average_order_value']['trend']));
        $this->applyTrendColor($sheet, "E{$row}", $kpis['average_order_value']['trend']);
        $this->applyNumberFormat($sheet, "B{$row}:C{$row}", $business['currency']);
        $row++;

        // Margin KPI
        $sheet->setCellValue("A{$row}", 'Profit Margin %');
        $sheet->setCellValue("B{$row}", $kpis['profit_margin']['current']);
        $sheet->setCellValue("C{$row}", $kpis['profit_margin']['previous']);
        $sheet->setCellValue("D{$row}", $kpis['profit_margin']['change_percent']);
        $sheet->setCellValue("E{$row}", strtoupper($kpis['profit_margin']['trend']));
        $this->applyTrendColor($sheet, "E{$row}", $kpis['profit_margin']['trend']);
        $row += 2;

        // Profit & Loss
        $sheet->setCellValue("A{$row}", 'PROFIT & LOSS STATEMENT');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $this->applyHeaderStyle($sheet, "A{$row}:B{$row}");
        $row++;

        $sheet->setCellValue("A{$row}", 'Revenue');
        $sheet->setCellValue("B{$row}", $profitLoss['revenue']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $this->applyCurrencyFormat($sheet, "B{$row}");
        $row++;

        $sheet->setCellValue("A{$row}", 'Cost of Goods Sold');
        $sheet->setCellValue("B{$row}", $profitLoss['cogs']);
        $this->applyCurrencyFormat($sheet, "B{$row}");
        $row++;

        $sheet->setCellValue("A{$row}", 'Gross Profit');
        $sheet->setCellValue("B{$row}", $profitLoss['gross_profit']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $this->applyCurrencyFormat($sheet, "B{$row}");
        $this->applyPositiveNegativeColor($sheet, "B{$row}", $profitLoss['gross_profit']);
        $row++;

        $sheet->setCellValue("A{$row}", 'Gross Margin %');
        $sheet->setCellValue("B{$row}", $profitLoss['gross_margin_percent']);
        $row++;

        $sheet->setCellValue("A{$row}", 'Operating Expenses');
        $sheet->setCellValue("B{$row}", $profitLoss['operating_expenses']);
        $this->applyCurrencyFormat($sheet, "B{$row}");
        $row++;

        $sheet->setCellValue("A{$row}", 'Net Profit');
        $sheet->setCellValue("B{$row}", $profitLoss['net_profit']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $this->applyCurrencyFormat($sheet, "B{$row}");
        $this->applyPositiveNegativeColor($sheet, "B{$row}", $profitLoss['net_profit']);
        $row++;

        $sheet->setCellValue("A{$row}", 'Net Margin %');
        $sheet->setCellValue("B{$row}", $profitLoss['net_margin_percent']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        // Auto-size columns
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Create product performance sheet
     */
    protected function createProductPerformanceSheet(Spreadsheet $spreadsheet, array $reportData): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Product Performance');

        $products = $reportData['product_performance'];
        $business = $reportData['business'];

        $row = 1;

        // Title
        $sheet->setCellValue("A{$row}", 'PRODUCT PERFORMANCE ANALYSIS');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $row += 2;

        // Headers
        $headers = ['Product Name', 'SKU', 'Units Sold', 'Revenue', 'Cost', 'Profit', 'Margin %', 'Transactions'];
        $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

        foreach ($headers as $index => $header) {
            $sheet->setCellValue("{$cols[$index]}{$row}", $header);
        }
        $this->applyTableHeaderStyle($sheet, $row);
        $row++;

        // Data
        foreach ($products as $product) {
            $sheet->setCellValue("A{$row}", $product['product_name']);
            $sheet->setCellValue("B{$row}", $product['sku'] ?? 'N/A');
            $sheet->setCellValue("C{$row}", $product['units_sold']);
            $sheet->setCellValue("D{$row}", $product['revenue']);
            $sheet->setCellValue("E{$row}", $product['cost']);
            $sheet->setCellValue("F{$row}", $product['profit']);
            $sheet->setCellValue("G{$row}", $product['profit_margin']);
            $sheet->setCellValue("H{$row}", $product['transaction_count']);

            // Format currency
            $this->applyCurrencyFormat($sheet, "D{$row}:F{$row}");

            // Color profit
            $this->applyPositiveNegativeColor($sheet, "F{$row}", $product['profit']);

            $row++;
        }

        // Totals
        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->setCellValue("C{$row}", "=SUM(C4:C" . ($row - 1) . ")");
        $sheet->setCellValue("D{$row}", "=SUM(D4:D" . ($row - 1) . ")");
        $sheet->setCellValue("E{$row}", "=SUM(E4:E" . ($row - 1) . ")");
        $sheet->setCellValue("F{$row}", "=SUM(F4:F" . ($row - 1) . ")");
        $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);
        $this->applyCurrencyFormat($sheet, "D{$row}:F{$row}");

        // Auto-size columns
        foreach ($cols as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Create insights and recommendations sheet
     */
    protected function createInsightsSheet(Spreadsheet $spreadsheet, array $reportData): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('AI Insights');

        $insights = $reportData['ai_insights'] ?? [];
        $recommendations = $reportData['recommendations'] ?? [];

        $row = 1;

        // Insights
        $sheet->setCellValue("A{$row}", 'AI-GENERATED INSIGHTS');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $row += 2;

        if (!empty($insights)) {
            // Headers
            $sheet->setCellValue("A{$row}", 'Category');
            $sheet->setCellValue("B{$row}", 'Title');
            $sheet->setCellValue("C{$row}", 'Description');
            $sheet->setCellValue("D{$row}", 'Severity');
            $this->applyTableHeaderStyle($sheet, $row);
            $row++;

            foreach ($insights as $insight) {
                $sheet->setCellValue("A{$row}", strtoupper($insight['category'] ?? 'General'));
                $sheet->setCellValue("B{$row}", $insight['title'] ?? '');
                $sheet->setCellValue("C{$row}", $insight['description'] ?? '');
                $sheet->setCellValue("D{$row}", strtoupper($insight['severity'] ?? 'Neutral'));

                // Color severity
                $severity = strtolower($insight['severity'] ?? 'neutral');
                if ($severity === 'positive') {
                    $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB('FF059669');
                } elseif ($severity === 'negative') {
                    $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB('FFDC2626');
                }

                $sheet->getStyle("C{$row}")->getAlignment()->setWrapText(true);
                $row++;
            }
        } else {
            $sheet->setCellValue("A{$row}", 'No insights available');
            $row++;
        }

        $row += 2;

        // Recommendations
        $sheet->setCellValue("A{$row}", 'STRATEGIC RECOMMENDATIONS');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $row += 2;

        if (!empty($recommendations)) {
            // Headers
            $sheet->setCellValue("A{$row}", 'Priority');
            $sheet->setCellValue("B{$row}", 'Title');
            $sheet->setCellValue("C{$row}", 'Action Required');
            $sheet->setCellValue("D{$row}", 'Expected Impact');
            $this->applyTableHeaderStyle($sheet, $row);
            $row++;

            foreach ($recommendations as $rec) {
                $sheet->setCellValue("A{$row}", strtoupper($rec['priority'] ?? 'Low'));
                $sheet->setCellValue("B{$row}", $rec['title'] ?? '');
                $sheet->setCellValue("C{$row}", $rec['description'] ?? '');
                $sheet->setCellValue("D{$row}", $rec['expected_impact'] ?? '');

                // Color priority
                $priority = strtolower($rec['priority'] ?? 'low');
                if ($priority === 'high') {
                    $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FFDC2626');
                    $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                } elseif ($priority === 'medium') {
                    $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FFF59E0B');
                    $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                }

                $sheet->getStyle("C{$row}:D{$row}")->getAlignment()->setWrapText(true);
                $row++;
            }
        } else {
            $sheet->setCellValue("A{$row}", 'No recommendations available');
            $row++;
        }

        // Auto-size columns
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(50);
        $sheet->getColumnDimension('D')->setWidth(40);
    }

    /**
     * Apply header style
     */
    protected function applyHeaderStyle($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1E40AF'],
            ],
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
        ]);
        $sheet->mergeCells($range);
    }

    /**
     * Apply table header style
     */
    protected function applyTableHeaderStyle($sheet, int $row): void
    {
        $sheet->getStyle("{$row}:{$row}")->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF3B82F6'],
            ],
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);
    }

    /**
     * Apply currency format
     */
    protected function applyCurrencyFormat($sheet, string $range): void
    {
        $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
    }

    /**
     * Apply number format for currency with symbol
     */
    protected function applyNumberFormat($sheet, string $range, string $currency): void
    {
        $sheet->getStyle($range)->getNumberFormat()->setFormatCode("{$currency} #,##0.00");
    }

    /**
     * Apply trend color
     */
    protected function applyTrendColor($sheet, string $cell, string $trend): void
    {
        $color = 'FF64748B'; // neutral
        if ($trend === 'up') {
            $color = 'FF059669'; // green
        } elseif ($trend === 'down') {
            $color = 'FFDC2626'; // red
        }

        $sheet->getStyle($cell)->getFont()->getColor()->setARGB($color);
        $sheet->getStyle($cell)->getFont()->setBold(true);
    }

    /**
     * Apply positive/negative color
     */
    protected function applyPositiveNegativeColor($sheet, string $cell, float $value): void
    {
        if ($value > 0) {
            $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FF059669');
        } elseif ($value < 0) {
            $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FFDC2626');
        }
    }
}
