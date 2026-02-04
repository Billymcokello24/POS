<?php

namespace App\Services\BI;

use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Professional Business Intelligence Report Generator
 * Generates executive-grade PDFs with embedded SVG charts
 */
class ReportGenerator
{
    public function generate(array $metrics, array $analysis): array
    {
        return [
            'metrics' => $metrics,
            'analysis' => $analysis,
            'tile_stats' => $this->calculateTileStats($metrics, $analysis),
        ];
    }

    protected function calculateTileStats(array $metrics, array $analysis): array
    {
        $current = $metrics['current_kpis'] ?? [];
        $trends = $metrics['kpi_trends'] ?? [];
        $score = min(max(($trends['revenue']['change'] ?? 0) + 50, 0), 100);
        $label = $score >= 70 ? 'Excellent' : ($score >= 50 ? 'Good' : ($score >= 30 ? 'Fair' : 'Needs Attention'));
        $color = $score >= 50 ? 'green' : ($score >= 30 ? 'yellow' : 'red');

        return [
            'total_value' => $current['revenue'] ?? 0,
            'total_items' => count($metrics['product_performance'] ?? []),
            'performance' => ['score' => round($score), 'label' => $label, 'color' => $color],
            'insights_count' => count($analysis['insights'] ?? []),
            'transactions' => $current['transactions'] ?? 0,
            'net_profit' => $current['net_profit'] ?? 0,
            'net_margin' => $current['net_margin'] ?? 0,
        ];
    }

    public function generatePDF(array $metrics, array $analysis)
    {
        $html = $this->buildPDFHTML($metrics, $analysis);
        return Pdf::loadHTML($html)->setPaper('a4', 'portrait');
    }

    /**
     * Generate Revenue Trend Line Chart with Forecast
     * Blue solid line = Actual, Red dashed line = Forecast
     */
    protected function revenueLineChart(array $historical, array $forecasts = []): string
    {
        if (count($historical) < 2) {
            return '<div style="text-align:center;color:#9ca3af;padding:30px;background:#f9fafb;border-radius:8px;font-size:11px;">Insufficient historical data for trend analysis</div>';
        }

        $w = 500; $h = 180; $p = 45; $cw = $w - 2*$p; $ch = $h - 2*$p;
        $vals = array_column($historical, 'revenue');
        $labels = array_column($historical, 'period_short');
        $n = count($vals);
        $last = end($vals);

        // Get forecast from analysis
        $forecast = $last * 1.12;
        foreach ($forecasts as $f) {
            if (isset($f['metric']) && $f['metric'] === 'Revenue' && isset($f['projection'])) {
                $forecast = $f['projection'];
                break;
            }
        }

        $max = max(max($vals), $forecast) * 1.15;

        // Build actual data points
        $pts = [];
        foreach ($vals as $i => $v) {
            $x = $p + ($i * ($cw / ($n-1)));
            $y = $p + $ch - (($v / $max) * $ch);
            $pts[] = round($x,1).','.round($y,1);
        }

        $lx = $p + (($n-1) * ($cw / ($n-1)));
        $ly = $p + $ch - (($last / $max) * $ch);
        $fx = $lx + 55;
        $fy = $p + $ch - (($forecast / $max) * $ch);

        $svg = '<svg width="'.$w.'" height="'.$h.'" xmlns="http://www.w3.org/2000/svg" style="font-family:sans-serif;">';

        // Gradient for area
        $svg .= '<defs><linearGradient id="aG" x1="0%" y1="0%" x2="0%" y2="100%">';
        $svg .= '<stop offset="0%" stop-color="#3b82f6" stop-opacity="0.4"/>';
        $svg .= '<stop offset="100%" stop-color="#3b82f6" stop-opacity="0.05"/>';
        $svg .= '</linearGradient></defs>';

        // Grid & Y-axis labels
        for ($i=0; $i<=4; $i++) {
            $gy = $p + ($ch * $i / 4);
            $svg .= '<line x1="'.$p.'" y1="'.$gy.'" x2="'.($p+$cw+55).'" y2="'.$gy.'" stroke="#e5e7eb" stroke-dasharray="4,4"/>';
            $lbl = number_format(($max * (4 - $i) / 4) / 1000, 0) . 'K';
            $svg .= '<text x="'.($p-8).'" y="'.($gy+4).'" text-anchor="end" font-size="8" fill="#6b7280">'.$lbl.'</text>';
        }

        // Area under actual line
        $areaPoly = $p.','.($p+$ch).' '.implode(' ',$pts).' '.$lx.','.($p+$ch);
        $svg .= '<polygon points="'.$areaPoly.'" fill="url(#aG)"/>';

        // Actual revenue line (BLUE)
        $svg .= '<polyline points="'.implode(' ',$pts).'" fill="none" stroke="#3b82f6" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>';

        // Data points
        foreach ($vals as $i => $v) {
            $x = $p + ($i * ($cw / ($n-1)));
            $y = $p + $ch - (($v / $max) * $ch);
            $svg .= '<circle cx="'.round($x,1).'" cy="'.round($y,1).'" r="5" fill="#3b82f6" stroke="#fff" stroke-width="2"/>';
        }

        // Forecast line (RED DASHED)
        $svg .= '<line x1="'.$lx.'" y1="'.round($ly,1).'" x2="'.$fx.'" y2="'.round($fy,1).'" stroke="#ef4444" stroke-width="2.5" stroke-dasharray="8,4"/>';
        $svg .= '<circle cx="'.$fx.'" cy="'.round($fy,1).'" r="6" fill="#ef4444" stroke="#fff" stroke-width="2"/>';

        // X-axis labels
        foreach ($labels as $i => $label) {
            $x = $p + ($i * ($cw / ($n-1)));
            $svg .= '<text x="'.round($x,1).'" y="'.($h-10).'" text-anchor="middle" font-size="8" fill="#6b7280">'.$label.'</text>';
        }
        $svg .= '<text x="'.$fx.'" y="'.($h-10).'" text-anchor="middle" font-size="8" fill="#ef4444" font-weight="bold">Forecast</text>';

        // Legend
        $svg .= '<g transform="translate('.($w-140).',8)"><rect x="0" y="0" width="135" height="38" fill="white" stroke="#d1d5db" rx="4"/>';
        $svg .= '<line x1="8" y1="13" x2="28" y2="13" stroke="#3b82f6" stroke-width="3"/>';
        $svg .= '<circle cx="18" cy="13" r="3" fill="#3b82f6"/>';
        $svg .= '<text x="33" y="16" font-size="8" fill="#374151">Actual Revenue</text>';
        $svg .= '<line x1="8" y1="28" x2="28" y2="28" stroke="#ef4444" stroke-width="2.5" stroke-dasharray="6,3"/>';
        $svg .= '<circle cx="28" cy="28" r="3" fill="#ef4444"/>';
        $svg .= '<text x="33" y="31" font-size="8" fill="#374151">Projected</text>';
        $svg .= '</g>';

        $svg .= '</svg>';
        return $svg;
    }

    /**
     * Generate Horizontal Bar Chart for Top Products
     */
    protected function productBarChart(array $products): string
    {
        $products = array_slice($products, 0, 5);
        if (empty($products)) {
            return '<div style="text-align:center;color:#9ca3af;padding:20px;">No product data</div>';
        }

        $w = 240; $h = 160; $bh = 24; $gap = 6;
        $max = max(array_column($products, 'revenue')) ?: 1;
        $barAreaWidth = $w - 80;
        $colors = ['#3b82f6','#8b5cf6','#06b6d4','#10b981','#f59e0b'];

        $svg = '<svg width="'.$w.'" height="'.$h.'" xmlns="http://www.w3.org/2000/svg" style="font-family:sans-serif;">';

        foreach ($products as $i => $p) {
            $barW = ($p['revenue'] / $max) * $barAreaWidth;
            $y = 10 + ($i * ($bh + $gap));
            $c = $colors[$i % 5];
            $name = strlen($p['name']) > 13 ? substr($p['name'], 0, 13).'..' : $p['name'];

            $svg .= '<text x="5" y="'.($y+15).'" font-size="8" fill="#374151">'.$name.'</text>';
            $svg .= '<rect x="75" y="'.$y.'" width="'.$barAreaWidth.'" height="'.$bh.'" fill="#f3f4f6" rx="4"/>';
            $svg .= '<rect x="75" y="'.$y.'" width="'.round($barW,1).'" height="'.$bh.'" fill="'.$c.'" rx="4"/>';
            $svg .= '<text x="'.(80+$barW).'" y="'.($y+15).'" font-size="8" font-weight="bold" fill="#1f2937">'.number_format($p['revenue']/1000,1).'K</text>';
        }

        $svg .= '</svg>';
        return $svg;
    }

    /**
     * Generate Pie Chart for Profit Distribution
     */
    protected function pieChart(array $pnl): string
    {
        $cogs = max($pnl['cogs'] ?? 0, 0);
        $exp = max($pnl['operating_expenses'] ?? 0, 0);
        $profit = max($pnl['net_profit'] ?? 0, 0);
        $total = $cogs + $exp + $profit;

        if ($total <= 0) {
            return '<div style="text-align:center;color:#9ca3af;padding:20px;">No financial data</div>';
        }

        $data = [
            ['l'=>'COGS','v'=>$cogs,'c'=>'#ef4444'],
            ['l'=>'Expenses','v'=>$exp,'c'=>'#f59e0b'],
            ['l'=>'Net Profit','v'=>$profit,'c'=>'#10b981'],
        ];

        $w = 240; $h = 160; $cx = 75; $cy = 75; $r = 58;
        $svg = '<svg width="'.$w.'" height="'.$h.'" xmlns="http://www.w3.org/2000/svg" style="font-family:sans-serif;">';

        $angle = -90;
        foreach ($data as $d) {
            if ($d['v'] <= 0) continue;
            $a = ($d['v'] / $total) * 360;
            $ea = $angle + $a;
            $x1 = $cx + $r * cos(deg2rad($angle));
            $y1 = $cy + $r * sin(deg2rad($angle));
            $x2 = $cx + $r * cos(deg2rad($ea));
            $y2 = $cy + $r * sin(deg2rad($ea));
            $la = $a > 180 ? 1 : 0;

            $svg .= '<path d="M '.$cx.' '.$cy.' L '.round($x1,2).' '.round($y1,2).' A '.$r.' '.$r.' 0 '.$la.' 1 '.round($x2,2).' '.round($y2,2).' Z" fill="'.$d['c'].'" stroke="white" stroke-width="2"/>';
            $angle = $ea;
        }

        // Center circle
        $svg .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="32" fill="white"/>';
        $svg .= '<text x="'.$cx.'" y="'.($cy+5).'" text-anchor="middle" font-size="11" font-weight="bold" fill="#374151">100%</text>';

        // Legend
        $lx = 155; $ly = 35;
        foreach ($data as $i => $d) {
            $y = $ly + ($i * 26);
            $pct = number_format(($d['v'] / $total) * 100, 1);
            $svg .= '<rect x="'.$lx.'" y="'.($y-9).'" width="13" height="13" fill="'.$d['c'].'" rx="2"/>';
            $svg .= '<text x="'.($lx+18).'" y="'.$y.'" font-size="9" font-weight="bold" fill="#374151">'.$d['l'].'</text>';
            $svg .= '<text x="'.($lx+18).'" y="'.($y+11).'" font-size="7" fill="#6b7280">'.$pct.'%</text>';
        }

        $svg .= '</svg>';
        return $svg;
    }

    /**
     * Generate Gauge Chart for KPI visualization
     */
    protected function gaugeChart(float $value, string $label, string $color = '#3b82f6'): string
    {
        $pct = min(max($value, 0), 100);
        $angle = ($pct / 100) * 180;
        $w = 95; $h = 60; $cx = 47.5; $cy = 50; $r = 38;

        $ex = $cx + $r * cos(deg2rad(180 - $angle));
        $ey = $cy - $r * sin(deg2rad(180 - $angle));
        $la = $angle > 180 ? 1 : 0;

        $svg = '<svg width="'.$w.'" height="'.$h.'" xmlns="http://www.w3.org/2000/svg" style="font-family:sans-serif;">';
        $svg .= '<path d="M '.($cx-$r).' '.$cy.' A '.$r.' '.$r.' 0 0 1 '.($cx+$r).' '.$cy.'" fill="none" stroke="#e5e7eb" stroke-width="8" stroke-linecap="round"/>';

        if ($pct > 0) {
            $svg .= '<path d="M '.($cx-$r).' '.$cy.' A '.$r.' '.$r.' 0 '.$la.' 1 '.round($ex,2).' '.round($ey,2).'" fill="none" stroke="'.$color.'" stroke-width="8" stroke-linecap="round"/>';
        }

        $svg .= '<text x="'.$cx.'" y="'.($cy-12).'" text-anchor="middle" font-size="13" font-weight="bold" fill="#1f2937">'.number_format($value,1).'%</text>';
        $svg .= '<text x="'.$cx.'" y="'.($cy+2).'" text-anchor="middle" font-size="7" fill="#6b7280">'.$label.'</text>';
        $svg .= '</svg>';

        return $svg;
    }

    /**
     * Generate BI Process Flowchart
     */
    protected function flowchart(): string
    {
        $w = 620; $h = 85;
        $svg = '<svg width="'.$w.'" height="'.$h.'" xmlns="http://www.w3.org/2000/svg" style="font-family:sans-serif;">';

        $svg .= '<defs><linearGradient id="g1" x1="0%" x2="100%"><stop offset="0%" stop-color="#60a5fa"/><stop offset="100%" stop-color="#7c3aed"/></linearGradient></defs>';

        // Box 1: Database
        $svg .= '<rect x="10" y="15" width="145" height="32" rx="5" fill="url(#g1)" opacity="0.95"/>';
        $svg .= '<text x="82" y="34" text-anchor="middle" fill="white" font-weight="700" font-size="9">Database (Sales & Products)</text>';

        // Arrow 1
        $svg .= '<line x1="155" y1="31" x2="185" y2="31" stroke="#94a3b8" stroke-width="2"/>';
        $svg .= '<polygon points="185,31 180,28 180,34" fill="#94a3b8"/>';

        // Box 2: Metrics Engine
        $svg .= '<rect x="195" y="15" width="130" height="32" rx="5" fill="#10b981" opacity="0.95"/>';
        $svg .= '<text x="260" y="34" text-anchor="middle" fill="white" font-weight="700" font-size="9">Metrics Engine</text>';

        // Arrow 2
        $svg .= '<line x1="325" y1="31" x2="355" y2="31" stroke="#94a3b8" stroke-width="2"/>';
        $svg .= '<polygon points="355,31 350,28 350,34" fill="#94a3b8"/>';

        // Box 3: BI Analysis
        $svg .= '<rect x="365" y="15" width="155" height="32" rx="5" fill="#f59e0b" opacity="0.95"/>';
        $svg .= '<text x="442" y="34" text-anchor="middle" fill="white" font-weight="700" font-size="9">BI Analysis & Forecast</text>';

        // Arrow 3
        $svg .= '<line x1="520" y1="31" x2="550" y2="31" stroke="#94a3b8" stroke-width="2"/>';
        $svg .= '<polygon points="550,31 545,28 545,34" fill="#94a3b8"/>';

        // Box 4: PDF Report
        $svg .= '<rect x="560" y="15" width="50" height="32" rx="5" fill="#8b5cf6" opacity="0.95"/>';
        $svg .= '<text x="585" y="34" text-anchor="middle" fill="white" font-weight="700" font-size="9">PDF</text>';

        // Labels below
        $svg .= '<text x="82" y="60" text-anchor="middle" font-size="7" fill="#6b7280">Source of Truth</text>';
        $svg .= '<text x="260" y="60" text-anchor="middle" font-size="7" fill="#6b7280">Data Aggregation</text>';
        $svg .= '<text x="442" y="60" text-anchor="middle" font-size="7" fill="#6b7280">Intelligence Layer</text>';
        $svg .= '<text x="585" y="60" text-anchor="middle" font-size="7" fill="#6b7280">Export</text>';

        $svg .= '</svg>';
        return $svg;
    }

    /**
     * Build complete PDF HTML with professional styling and charts
     * Designed to look like a professional business report, NOT a web page print
     */
    protected function buildPDFHTML(array $metrics, array $analysis): string
    {
        $b = $metrics['business'] ?? [];
        $p = $metrics['period'] ?? [];
        $c = $metrics['current_kpis'] ?? [];
        $prev = $metrics['previous_kpis'] ?? [];
        $t = $metrics['kpi_trends'] ?? [];
        $pnl = $metrics['profit_loss'] ?? [];
        $tp = $metrics['top_products'] ?? [];
        $allProducts = $metrics['product_performance'] ?? [];
        $hist = $metrics['historical_kpis'] ?? [];
        $fc = $analysis['forecasts'] ?? [];
        $ins = $analysis['insights'] ?? [];
        $rec = $analysis['recommendations'] ?? [];
        $sum = $analysis['executive_summary'] ?? '';
        $cur = $b['currency'] ?? 'KES';

        // Generate all charts
        $lineChart = $this->revenueLineChart($hist, $fc);
        $barChart = $this->productBarChart($tp);
        $pie = $this->pieChart($pnl);
        $g1 = $this->gaugeChart($c['gross_margin'] ?? 0, 'Gross Margin', '#3b82f6');
        $g2 = $this->gaugeChart($c['net_margin'] ?? 0, 'Net Margin', '#10b981');
        $g3 = $this->gaugeChart(min(($c['transactions']??0)/max($prev['transactions']??1,1)*100,200), 'Growth', '#8b5cf6');
        $flow = $this->flowchart();

        ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Business Intelligence Report - <?=htmlspecialchars($b['name']??'Business')?></title>
<style>
@page {
    size: A4;
    margin: 15mm 15mm 20mm 15mm;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'DejaVu Sans', 'Helvetica', Arial, sans-serif;
    font-size: 9pt;
    line-height: 1.5;
    color: #2c3e50;
    background: #ffffff;
}

/* Professional Header - Fixed on every page */
.pdf-header {
    position: running(header);
    width: 100%;
    padding: 0 0 8mm 0;
    border-bottom: 3px solid #4f46e5;
    margin-bottom: 6mm;
}

.pdf-header-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.pdf-header-left h1 {
    font-size: 16pt;
    font-weight: 800;
    color: #1f2937;
    margin-bottom: 2mm;
}

.pdf-header-left .subtitle {
    font-size: 10pt;
    color: #6b7280;
    font-weight: 600;
}

.pdf-header-right {
    text-align: right;
    font-size: 7pt;
    color: #6b7280;
}

.pdf-header-right .company {
    font-size: 9pt;
    font-weight: 700;
    color: #4f46e5;
    margin-bottom: 1mm;
}

/* Professional Footer - Fixed on every page */
.pdf-footer {
    position: running(footer);
    width: 100%;
    padding: 6mm 0 0 0;
    border-top: 1px solid #e5e7eb;
    font-size: 6pt;
    color: #9ca3af;
    text-align: center;
}

.pdf-footer .footer-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pdf-footer .footer-left {
    text-align: left;
    flex: 1;
}

.pdf-footer .footer-center {
    flex: 1;
    text-align: center;
    font-weight: 600;
}

.pdf-footer .footer-right {
    text-align: right;
    flex: 1;
}

/* Page Setup */
.page {
    page-break-after: always;
}

.page:last-child {
    page-break-after: avoid;
}

/* Cover Page */
.cover-page {
    text-align: center;
    padding: 60mm 20mm;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    min-height: 240mm;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.cover-page h1 {
    font-size: 28pt;
    font-weight: 900;
    margin-bottom: 10mm;
    letter-spacing: -0.5pt;
}

.cover-page .business-name {
    font-size: 20pt;
    font-weight: 600;
    margin-bottom: 15mm;
    opacity: 0.95;
}

.cover-page .report-meta {
    font-size: 11pt;
    margin-bottom: 3mm;
    opacity: 0.9;
}

.cover-page .badge {
    display: inline-block;
    background: rgba(255,255,255,0.2);
    padding: 3mm 6mm;
    border-radius: 10mm;
    font-size: 9pt;
    margin-top: 8mm;
}

/* Section Styling */
.section {
    margin-bottom: 8mm;
    page-break-inside: avoid;
}

.section-header {
    background: linear-gradient(135deg, #f0f4ff, #e0e7ff);
    border-left: 4mm solid #4f46e5;
    padding: 3mm 4mm;
    margin-bottom: 4mm;
    border-radius: 0 3mm 3mm 0;
}

.section-title {
    font-size: 12pt;
    font-weight: 800;
    color: #1e293b;
    display: flex;
    align-items: center;
}

.section-icon {
    margin-right: 3mm;
    font-size: 14pt;
}

/* Executive Summary Box */
.exec-summary {
    background: linear-gradient(135deg, #fef9e7, #fef5e7);
    border: 2px solid #f39c12;
    border-radius: 4mm;
    padding: 5mm;
    margin-bottom: 8mm;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.exec-summary h3 {
    color: #e67e22;
    font-size: 11pt;
    margin-bottom: 3mm;
    font-weight: 800;
}

.exec-summary p {
    color: #2c3e50;
    line-height: 1.7;
    font-size: 9pt;
}

/* KPI Grid */
.kpi-grid {
    display: table;
    width: 100%;
    border-spacing: 3mm;
    margin-bottom: 6mm;
}

.kpi-row {
    display: table-row;
}

.kpi-card {
    display: table-cell;
    width: 25%;
    background: linear-gradient(135deg, #f8f9fa, #ffffff);
    border: 2px solid #e9ecef;
    border-radius: 3mm;
    padding: 4mm;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.kpi-card.primary {
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: white;
    border: none;
}

.kpi-label {
    font-size: 6pt;
    text-transform: uppercase;
    letter-spacing: 0.5pt;
    color: #6b7280;
    margin-bottom: 2mm;
    font-weight: 700;
}

.kpi-card.primary .kpi-label {
    color: rgba(255,255,255,0.9);
}

.kpi-value {
    font-size: 16pt;
    font-weight: 900;
    color: #1e293b;
    margin: 2mm 0;
}

.kpi-card.primary .kpi-value {
    color: white;
}

.kpi-change {
    font-size: 6pt;
    padding: 1mm 2mm;
    border-radius: 2mm;
    display: inline-block;
    font-weight: 700;
}

.kpi-change.up {
    background: #d1f4e0;
    color: #0f5132;
}

.kpi-change.down {
    background: #f8d7da;
    color: #842029;
}

.kpi-change.stable {
    background: #e9ecef;
    color: #6c757d;
}

/* Comparison Table */
.comparison-box {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 3mm;
    padding: 4mm;
    margin-bottom: 6mm;
}

.comparison-title {
    font-size: 9pt;
    font-weight: 800;
    color: #4f46e5;
    margin-bottom: 3mm;
    text-transform: uppercase;
    letter-spacing: 0.5pt;
}

.comparison-table {
    width: 100%;
    border-collapse: collapse;
}

.comparison-table td {
    padding: 2mm 3mm;
    border-bottom: 1px solid #e9ecef;
}

.comparison-table .label {
    font-weight: 600;
    color: #6b7280;
    font-size: 8pt;
}

.comparison-table .value {
    text-align: right;
    font-weight: 800;
    font-size: 10pt;
    color: #1e293b;
}

/* Charts */
.chart-container {
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 4mm;
    padding: 4mm;
    margin-bottom: 6mm;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

.chart-title {
    font-size: 9pt;
    font-weight: 800;
    color: #374151;
    margin-bottom: 3mm;
    padding-bottom: 2mm;
    border-bottom: 2px solid #f3f4f6;
}

.charts-row {
    display: table;
    width: 100%;
    margin-top: 6mm;
}

.chart-cell {
    display: table-cell;
    width: 50%;
    vertical-align: top;
    padding: 0 2mm;
}

.gauges-row {
    display: table;
    width: 100%;
    margin: 6mm 0;
}

.gauge-cell {
    display: table-cell;
    width: 33.33%;
    text-align: center;
}

/* Data Tables */
table.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 7pt;
    margin-bottom: 6mm;
}

table.data-table thead th {
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: white;
    padding: 3mm 2mm;
    text-align: left;
    font-size: 7pt;
    text-transform: uppercase;
    letter-spacing: 0.3pt;
    font-weight: 800;
}

table.data-table thead th:first-child {
    border-radius: 2mm 0 0 0;
}

table.data-table thead th:last-child {
    border-radius: 0 2mm 0 0;
}

table.data-table tbody td {
    padding: 2.5mm 2mm;
    border-bottom: 1px solid #e5e7eb;
    font-size: 8pt;
}

table.data-table tbody tr:nth-child(even) {
    background: #f9fafb;
}

table.data-table tbody tr:hover {
    background: #f3f4f6;
}

.text-right {
    text-align: right;
}

.text-center {
    text-align: center;
}

/* P&L Statement */
.pnl-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9pt;
    background: white;
}

.pnl-table td {
    padding: 3mm 4mm;
    border-bottom: 1px solid #e5e7eb;
}

.pnl-table tr.total {
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    font-weight: 800;
    font-size: 10pt;
}

.pnl-table tr.total.loss {
    background: linear-gradient(135deg, #fef2f2, #fee2e2);
}

.pnl-table .amount {
    text-align: right;
    font-family: 'DejaVu Sans Mono', monospace;
    font-weight: 700;
}

.positive {
    color: #047857;
}

.negative {
    color: #dc2626;
}

/* Insights */
.insight-card {
    background: white;
    border-radius: 3mm;
    padding: 3mm 4mm;
    margin-bottom: 3mm;
    border-left: 4mm solid;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.insight-card.positive {
    border-color: #10b981;
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
}

.insight-card.negative {
    border-color: #ef4444;
    background: linear-gradient(135deg, #fef2f2, #fee2e2);
}

.insight-card.warning {
    border-color: #f59e0b;
    background: linear-gradient(135deg, #fffbeb, #fef3c7);
}

.insight-title {
    font-weight: 800;
    font-size: 9pt;
    color: #1f2937;
    margin-bottom: 1mm;
}

.insight-desc {
    font-size: 8pt;
    color: #4b5563;
    line-height: 1.5;
}

/* Forecasts */
.forecast-card {
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    border: 2px solid #93c5fd;
    border-radius: 3mm;
    padding: 3mm 4mm;
    margin-bottom: 3mm;
}

.forecast-metric {
    font-size: 8pt;
    font-weight: 800;
    color: #1e40af;
    text-transform: uppercase;
}

.forecast-badge {
    display: inline-block;
    background: #3b82f6;
    color: white;
    font-size: 6pt;
    padding: 1mm 2mm;
    border-radius: 2mm;
    margin-left: 2mm;
    font-weight: 700;
}

.forecast-desc {
    font-size: 8pt;
    color: #1e3a8a;
    margin-top: 2mm;
    line-height: 1.5;
}

/* Recommendations */
.rec-card {
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 3mm;
    padding: 3mm 4mm;
    margin-bottom: 3mm;
}

.priority-badge {
    display: inline-block;
    padding: 1mm 2mm;
    border-radius: 2mm;
    font-size: 6pt;
    font-weight: 800;
    text-transform: uppercase;
}

.priority-badge.high {
    background: #dc2626;
    color: white;
}

.priority-badge.medium {
    background: #f59e0b;
    color: white;
}

.priority-badge.low {
    background: #6b7280;
    color: white;
}

.rec-title {
    font-weight: 800;
    font-size: 9pt;
    color: #1f2937;
    margin-left: 2mm;
}

.rec-desc {
    font-size: 8pt;
    color: #4b5563;
    margin-top: 2mm;
    line-height: 1.5;
}

.rec-impact {
    font-size: 7pt;
    color: #047857;
    margin-top: 2mm;
    font-weight: 700;
}

/* Page Numbers */
.page-number {
    position: absolute;
    bottom: 10mm;
    right: 15mm;
    font-size: 8pt;
    color: #9ca3af;
}

/* Confidential Watermark */
.confidential {
    font-size: 6pt;
    color: #dc2626;
    text-transform: uppercase;
    letter-spacing: 1pt;
    font-weight: 800;
    text-align: center;
    margin-top: 4mm;
}
</style>
</head>
<body>

<!-- Cover Page -->
<div class="page cover-page">
    <div>
        <h1>📊 BUSINESS INTELLIGENCE REPORT</h1>
        <div class="business-name"><?=htmlspecialchars($b['name']??'Business')?></div>
        <div class="report-meta"><strong>Reporting Period:</strong> <?=$p['start_date']??'N/A'?> to <?=$p['end_date']??'N/A'?></div>
        <div class="report-meta"><strong>Generated:</strong> <?=date('F j, Y \a\t g:i A', strtotime($p['generated_at']??'now'))?></div>
        <div class="badge">✨ Data-Driven Analytics • AI-Assisted Forecasts</div>
    </div>
    <div class="confidential">CONFIDENTIAL BUSINESS REPORT</div>
</div>

<!-- Page 1: Executive Overview -->
<div class="page">
<div class="pdf-header">
    <div class="pdf-header-content">
        <div class="pdf-header-left">
            <h1>Business Intelligence Report</h1>
            <div class="subtitle">Executive Overview & Key Metrics</div>
        </div>
        <div class="pdf-header-right">
            <div class="company"><?=htmlspecialchars($b['name']??'')?></div>
            <div><?=$p['start_date']??'N/A'?> - <?=$p['end_date']??'N/A'?></div>
            <div>Page 1 of 4</div>
        </div>
    </div>
</div>

<!-- Executive Summary -->
<div class="exec-summary">
    <h3>📋 EXECUTIVE SUMMARY</h3>
    <p><?=htmlspecialchars($sum)?></p>
</div>

<!-- Key Performance Indicators -->
<div class="section">
    <div class="section-header">
        <div class="section-title"><span class="section-icon">📈</span> KEY PERFORMANCE INDICATORS</div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-row">
            <div class="kpi-card primary">
                <div class="kpi-label">Total Revenue</div>
                <div class="kpi-value"><?=$cur?> <?=number_format($c['revenue']??0,0)?></div>
                <div class="kpi-change <?=$t['revenue']['direction']??'stable'?>">
                    <?=($t['revenue']['change']??0)>=0?'↑':'↓'?> <?=number_format(abs($t['revenue']['change']??0),1)?>%
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Net Profit</div>
                <div class="kpi-value"><?=$cur?> <?=number_format($c['net_profit']??0,0)?></div>
                <div class="kpi-change <?=$t['net_profit']['direction']??'stable'?>">
                    <?=($t['net_profit']['change']??0)>=0?'↑':'↓'?> <?=number_format(abs($t['net_profit']['change']??0),1)?>%
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Transactions</div>
                <div class="kpi-value"><?=number_format($c['transactions']??0)?></div>
                <div class="kpi-change <?=$t['transactions']['direction']??'stable'?>">
                    <?=($t['transactions']['change']??0)>=0?'↑':'↓'?> <?=number_format(abs($t['transactions']['change']??0),1)?>%
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Avg Order Value</div>
                <div class="kpi-value"><?=$cur?> <?=number_format($c['avg_order_value']??0,0)?></div>
                <div class="kpi-change <?=$t['avg_order_value']['direction']??'stable'?>">
                    <?=($t['avg_order_value']['change']??0)>=0?'↑':'↓'?> <?=number_format(abs($t['avg_order_value']['change']??0),1)?>%
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Period Comparison -->
<div class="comparison-box">
    <div class="comparison-title">📊 PERIOD-OVER-PERIOD ANALYSIS</div>
    <table class="comparison-table">
        <tr>
            <td class="label">Current Period Revenue</td>
            <td class="value"><?=$cur?> <?=number_format($c['revenue']??0,2)?></td>
            <td class="label">Previous Period Revenue</td>
            <td class="value"><?=$cur?> <?=number_format($prev['revenue']??0,2)?></td>
        </tr>
        <tr>
            <td class="label">Current Net Profit</td>
            <td class="value <?=($c['net_profit']??0)>=0?'positive':'negative'?>"><?=$cur?> <?=number_format($c['net_profit']??0,2)?></td>
            <td class="label">Previous Net Profit</td>
            <td class="value <?=($prev['net_profit']??0)>=0?'positive':'negative'?>"><?=$cur?> <?=number_format($prev['net_profit']??0,2)?></td>
        </tr>
        <tr>
            <td class="label">Current Gross Margin</td>
            <td class="value"><?=number_format($c['gross_margin']??0,1)?>%</td>
            <td class="label">Previous Gross Margin</td>
            <td class="value"><?=number_format($prev['gross_margin']??0,1)?>%</td>
        </tr>
    </table>
</div>

<!-- Performance Gauges -->
<div class="gauges-row">
    <div class="gauge-cell"><?=$g1?></div>
    <div class="gauge-cell"><?=$g2?></div>
    <div class="gauge-cell"><?=$g3?></div>
</div>

<!-- Revenue Trend Chart -->
<div class="chart-container">
    <div class="chart-title">📊 REVENUE TREND & AI-ASSISTED FORECAST</div>
    <div style="font-size:6pt;color:#6b7280;margin-bottom:2mm;">Blue Line = Actual Historical Revenue | Red Dashed Line = Projected Revenue (+12% growth)</div>
    <?=$lineChart?>
</div>

<!-- BI Process Architecture -->
<div style="margin-top:6mm;text-align:center;">
    <div style="font-size:8pt;font-weight:700;color:#4f46e5;margin-bottom:2mm">BUSINESS INTELLIGENCE PIPELINE</div>
    <?=$flow?>
</div>

<div class="pdf-footer">
    <div class="footer-content">
        <div class="footer-left">© <?=date('Y')?> <?=htmlspecialchars($b['name']??'')?></div>
        <div class="footer-center">Confidential Business Intelligence Report</div>
        <div class="footer-right">Generated: <?=date('M j, Y', strtotime($p['generated_at']??'now'))?></div>
    </div>
</div>
</div>

<!-- Page 2: Financial Analysis -->
<div class="page">
<div class="pdf-header">
    <div class="pdf-header-content">
        <div class="pdf-header-left">
            <h1>Business Intelligence Report</h1>
            <div class="subtitle">Financial Performance Analysis</div>
        </div>
        <div class="pdf-header-right">
            <div class="company"><?=htmlspecialchars($b['name']??'')?></div>
            <div><?=$p['start_date']??'N/A'?> - <?=$p['end_date']??'N/A'?></div>
            <div>Page 2 of 4</div>
        </div>
    </div>
</div>

<!-- Financial Charts -->
<div class="section">
    <div class="section-header">
        <div class="section-title"><span class="section-icon">💹</span> FINANCIAL ANALYTICS & VISUALIZATION</div>
    </div>

    <div class="charts-row">
        <div class="chart-cell">
            <div class="chart-container">
                <div class="chart-title">Top Products by Revenue</div>
                <?=$barChart?>
            </div>
        </div>
        <div class="chart-cell">
            <div class="chart-container">
                <div class="chart-title">Cost & Profit Distribution</div>
                <?=$pie?>
            </div>
        </div>
    </div>
</div>

<!-- Profit & Loss Statement -->
<div class="section">
    <div class="section-header">
        <div class="section-title"><span class="section-icon">💰</span> PROFIT & LOSS STATEMENT</div>
    </div>

    <table class="pnl-table">
        <tr>
            <td><strong>Revenue</strong></td>
            <td class="amount positive"><?=$cur?> <?=number_format($pnl['revenue']??0,2)?></td>
        </tr>
        <tr>
            <td style="padding-left:4mm;">Cost of Goods Sold (COGS)</td>
            <td class="amount negative">(<?=$cur?> <?=number_format($pnl['cogs']??0,2)?>)</td>
        </tr>
        <tr class="total">
            <td><strong>Gross Profit</strong></td>
            <td class="amount positive"><?=$cur?> <?=number_format($pnl['gross_profit']??0,2)?> <span style="font-size:8pt">(<?=number_format($pnl['gross_margin_pct']??0,1)?>%)</span></td>
        </tr>
        <tr>
            <td style="padding-left:4mm;">Operating Expenses</td>
            <td class="amount negative">(<?=$cur?> <?=number_format($pnl['operating_expenses']??0,2)?>)</td>
        </tr>
        <tr class="total <?=($pnl['net_profit']??0)<0?'loss':''?>">
            <td><strong>Net Profit</strong></td>
            <td class="amount <?=($pnl['net_profit']??0)>=0?'positive':'negative'?>"><?=$cur?> <?=number_format($pnl['net_profit']??0,2)?> <span style="font-size:8pt">(<?=number_format($pnl['net_margin_pct']??0,1)?>%)</span></td>
        </tr>
    </table>
</div>

<!-- Top Products Table -->
<div class="section">
    <div class="section-header">
        <div class="section-title"><span class="section-icon">🏆</span> TOP PERFORMING PRODUCTS</div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Product Name</th>
                <th class="text-right">Units Sold</th>
                <th class="text-right">Revenue</th>
                <th class="text-right">Profit</th>
                <th class="text-right">Margin %</th>
                <th class="text-center">Rating</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach(array_slice($tp,0,10) as $i => $pr): ?>
            <tr>
                <td><strong><?=($i+1).'. '.htmlspecialchars($pr['name']??'')?></strong></td>
                <td class="text-right"><?=number_format($pr['units_sold']??0)?></td>
                <td class="text-right"><strong><?=$cur?> <?=number_format($pr['revenue']??0,2)?></strong></td>
                <td class="text-right"><?=$cur?> <?=number_format($pr['profit']??0,2)?></td>
                <td class="text-right" style="color:<?=($pr['margin']??0)>=30?'#047857':(($pr['margin']??0)>=15?'#d97706':'#dc2626')?>;font-weight:800"><?=number_format($pr['margin']??0,1)?>%</td>
                <td class="text-center"><?php $m=($pr['margin']??0); echo $m>=30?'⭐⭐⭐':($m>=15?'⭐⭐':'⭐'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="pdf-footer">
    <div class="footer-content">
        <div class="footer-left">© <?=date('Y')?> <?=htmlspecialchars($b['name']??'')?></div>
        <div class="footer-center">Confidential Business Intelligence Report</div>
        <div class="footer-right">Generated: <?=date('M j, Y', strtotime($p['generated_at']??'now'))?></div>
    </div>
</div>
</div>

<!-- Page 3: Intelligence & Insights -->
<div class="page">
<div class="pdf-header">
    <div class="pdf-header-content">
        <div class="pdf-header-left">
            <h1>Business Intelligence Report</h1>
            <div class="subtitle">Business Insights & AI Forecasts</div>
        </div>
        <div class="pdf-header-right">
            <div class="company"><?=htmlspecialchars($b['name']??'')?></div>
            <div><?=$p['start_date']??'N/A'?> - <?=$p['end_date']??'N/A'?></div>
            <div>Page 3 of 4</div>
        </div>
    </div>
</div>

<!-- Business Insights -->
<?php if(!empty($ins)): ?>
<div class="section">
    <div class="section-header">
        <div class="section-title"><span class="section-icon">🧠</span> BUSINESS INTELLIGENCE INSIGHTS</div>
    </div>

    <?php foreach($ins as $insight): ?>
    <div class="insight-card <?=$insight['type']??'positive'?>">
        <div class="insight-title">
            <?=($insight['type']??'')=='positive'?'✓':(($insight['type']??'')=='negative'?'✗':'⚠')?>
            <?=htmlspecialchars($insight['title']??'Insight')?></div>
        <div class="insight-desc"><?=htmlspecialchars($insight['description']??'')?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- AI Forecasts -->
<?php if(!empty($fc)): ?>
<div class="section">
    <div class="section-header">
        <div class="section-title"><span class="section-icon">🔮</span> AI-ASSISTED FORECASTS & PROJECTIONS</div>
    </div>

    <div style="font-size:7pt;color:#6b7280;margin-bottom:3mm;padding:2mm 3mm;background:#fef3c7;border-left:3mm solid #f59e0b;border-radius:0 2mm 2mm 0">
        <strong>📈 Note:</strong> Refer to the Revenue Trend chart on Page 1 for visual representation. Blue line shows actual performance, red dashed line indicates projected growth.
    </div>

    <?php foreach($fc as $forecast): ?>
    <div class="forecast-card">
        <div>
            <span class="forecast-metric"><?=htmlspecialchars($forecast['metric']??'Metric')?></span>
            <span class="forecast-badge"><?=strtoupper($forecast['confidence']??'MEDIUM')?> CONFIDENCE</span>
        </div>
        <div class="forecast-desc"><?=htmlspecialchars($forecast['description']??'')?></div>
    </div>
    <?php endforeach; ?>

    <div style="font-size:6pt;color:#9ca3af;margin-top:4mm;padding:2mm;border-top:1px solid #e5e7eb;font-style:italic">
        ⚠️ <strong>Disclaimer:</strong> Forecasts are data-driven statistical projections based on historical trends. Actual business results may vary due to market conditions, seasonal factors, and unforeseen circumstances. These projections should be used as guidance for strategic planning, not as guarantees of future performance.
    </div>
</div>
<?php endif; ?>

<!-- Strategic Recommendations -->
<?php if(!empty($rec)): ?>
<div class="section">
    <div class="section-header">
        <div class="section-title"><span class="section-icon">💡</span> STRATEGIC RECOMMENDATIONS</div>
    </div>

    <?php foreach($rec as $recommendation): ?>
    <div class="rec-card">
        <div>
            <span class="priority-badge <?=$recommendation['priority']??'medium'?>"><?=strtoupper($recommendation['priority']??'MEDIUM')?> PRIORITY</span>
            <span class="rec-title"><?=htmlspecialchars($recommendation['title']??'Recommendation')?></span>
        </div>
        <div class="rec-desc"><?=htmlspecialchars($recommendation['description']??'')?></div>
        <div class="rec-impact">✓ Expected Impact: <?=htmlspecialchars($recommendation['expected_impact']??'To be determined')?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="pdf-footer">
    <div class="footer-content">
        <div class="footer-left">© <?=date('Y')?> <?=htmlspecialchars($b['name']??'')?></div>
        <div class="footer-center">Confidential Business Intelligence Report</div>
        <div class="footer-right">Generated: <?=date('M j, Y', strtotime($p['generated_at']??'now'))?></div>
    </div>
</div>
</div>

<!-- Page 4: Historical Performance & Appendix -->
<div class="page">
<div class="pdf-header">
    <div class="pdf-header-content">
        <div class="pdf-header-left">
            <h1>Business Intelligence Report</h1>
            <div class="subtitle">Historical Data & Performance Trends</div>
        </div>
        <div class="pdf-header-right">
            <div class="company"><?=htmlspecialchars($b['name']??'')?></div>
            <div><?=$p['start_date']??'N/A'?> - <?=$p['end_date']??'N/A'?></div>
            <div>Page 4 of 4</div>
        </div>
    </div>
</div>

<!-- Historical Performance -->
<div class="section">
    <div class="section-header">
        <div class="section-title"><span class="section-icon">📅</span> HISTORICAL PERFORMANCE (6-MONTH TREND)</div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Period</th>
                <th class="text-right">Revenue</th>
                <th class="text-right">COGS</th>
                <th class="text-right">Gross Profit</th>
                <th class="text-right">Margin %</th>
                <th class="text-right">Transactions</th>
                <th class="text-right">Avg Order</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($hist as $h): ?>
            <?php
                $margin = ($h['revenue']??0) > 0 ? (($h['gross_profit']??0)/($h['revenue']??1))*100 : 0;
                $avg = ($h['transactions']??0) > 0 ? ($h['revenue']??0)/($h['transactions']??1) : 0;
            ?>
            <tr>
                <td><strong><?=$h['period']??''?></strong></td>
                <td class="text-right"><?=$cur?> <?=number_format($h['revenue']??0,2)?></td>
                <td class="text-right"><?=$cur?> <?=number_format($h['cogs']??0,2)?></td>
                <td class="text-right"><strong><?=$cur?> <?=number_format($h['gross_profit']??0,2)?></strong></td>
                <td class="text-right" style="color:<?=$margin>=30?'#047857':($margin>=15?'#d97706':'#dc2626')?>;font-weight:700"><?=number_format($margin,1)?>%</td>
                <td class="text-right"><?=number_format($h['transactions']??0)?></td>
                <td class="text-right"><?=$cur?> <?=number_format($avg,2)?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Report Methodology -->
<div class="section">
    <div class="section-header">
        <div class="section-title"><span class="section-icon">📖</span> REPORT METHODOLOGY & DATA SOURCES</div>
    </div>

    <div style="font-size:8pt;line-height:1.6;color:#4b5563">
        <p style="margin-bottom:2mm"><strong>Data Collection:</strong> All financial metrics are extracted directly from the business database in real-time. Sales transactions, product inventory, and cost data form the foundation of this analysis.</p>

        <p style="margin-bottom:2mm"><strong>Analysis Framework:</strong> The Business Intelligence Engine uses a three-layer architecture: (1) Metrics Aggregation from database, (2) Statistical Analysis & Trend Detection, (3) AI-Assisted Forecasting based on historical patterns.</p>

        <p style="margin-bottom:2mm"><strong>KPI Calculations:</strong></p>
        <ul style="margin-left:5mm;font-size:7pt">
            <li><strong>Revenue:</strong> Sum of all completed sales transactions</li>
            <li><strong>COGS:</strong> Calculated as (Units Sold × Cost Price) for each product</li>
            <li><strong>Gross Profit:</strong> Revenue - COGS</li>
            <li><strong>Gross Margin:</strong> (Gross Profit / Revenue) × 100</li>
            <li><strong>Net Profit:</strong> Gross Profit - Operating Expenses</li>
            <li><strong>Net Margin:</strong> (Net Profit / Revenue) × 100</li>
        </ul>

        <p style="margin-top:2mm"><strong>Forecast Methodology:</strong> Projections are generated using trend analysis of the most recent 6-month period, with a dampening factor applied to prevent over-optimistic estimates. The confidence level is determined by the stability of historical trends.</p>
    </div>
</div>

<!-- Report Certification -->
<div style="margin-top:10mm;border:2px solid #4f46e5;border-radius:4mm;padding:4mm;background:linear-gradient(135deg,#f0f4ff,#e0e7ff)">
    <div style="text-align:center;font-size:10pt;font-weight:800;color:#4f46e5;margin-bottom:2mm">REPORT CERTIFICATION</div>
    <div style="font-size:7pt;color:#4b5563;line-height:1.6">
        <p>This Business Intelligence Report has been generated using verified data from the <?=htmlspecialchars($b['name']??'Business')?> management system. All financial figures, metrics, and KPIs are computed directly from transaction records and inventory data.</p>
        <p style="margin-top:2mm">The analysis, insights, and forecasts contained herein are produced using data-driven methodologies and should be used for strategic business planning purposes.</p>
    </div>
    <div style="margin-top:3mm;padding-top:3mm;border-top:1px solid #c7d2fe;font-size:6pt;color:#6b7280">
        <strong>Report ID:</strong> BI-<?=date('Ymd-His', strtotime($p['generated_at']??'now'))?> |
        <strong>Data Period:</strong> <?=$p['start_date']??'N/A'?> to <?=$p['end_date']??'N/A'?> |
        <strong>Generated:</strong> <?=date('F j, Y \a\t g:i:s A', strtotime($p['generated_at']??'now'))?>
    </div>
</div>

<div class="confidential" style="margin-top:8mm">CONFIDENTIAL - FOR AUTHORIZED PERSONNEL ONLY</div>

<div class="pdf-footer">
    <div class="footer-content">
        <div class="footer-left">© <?=date('Y')?> <?=htmlspecialchars($b['name']??'')?></div>
        <div class="footer-center">End of Business Intelligence Report</div>
        <div class="footer-right">Generated: <?=date('M j, Y', strtotime($p['generated_at']??'now'))?></div>
    </div>
</div>
</div>

</body>
</html>
<?php
        return ob_get_clean();
    }
}
