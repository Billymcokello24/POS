<?php

namespace App\Http\Controllers;

use App\Services\BI\MetricsEngine;
use App\Services\BI\IntelligenceEngine;
use App\Services\BI\ReportGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Business Intelligence Controller
 *
 * Handles BI report generation and export
 */
class BusinessIntelligenceController extends Controller
{
    /**
     * Show BI dashboard
     */
    public function index()
    {
        return Inertia::render('Reports/BusinessIntelligence');
    }

    /**
     * Generate BI report (JSON API)
     */
    public function generate(Request $request)
    {
        try {
            $request->validate([
                'period' => 'required|in:today,week,month,quarter,year,all',
            ]);

            $user = auth()->user();
            $businessId = $user->current_business_id;

            if (!$businessId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No business selected',
                ], 400);
            }

            $period = $request->input('period', 'month');

            // Step 1: Get metrics from database (Source of Truth)
            $metricsEngine = new MetricsEngine($businessId, $period);
            $metrics = $metricsEngine->getMetrics();

            // Step 2: Generate intelligence (AI analysis)
            $intelligenceEngine = new IntelligenceEngine();
            $analysis = $intelligenceEngine->analyze($metrics);

            // Step 3: Compile report
            $reportGenerator = new ReportGenerator();
            $report = $reportGenerator->generate($metrics, $analysis);

            Log::info('BI Report Generated', [
                'business_id' => $businessId,
                'period' => $period,
                'analysis_type' => $analysis['analysis_type'] ?? 'unknown',
            ]);

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);

        } catch (\Exception $e) {
            Log::error('BI Report Generation Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export BI report as PDF
     */
    public function exportPDF(Request $request)
    {
        try {
            $request->validate([
                'period' => 'required|in:today,week,month,quarter,year,all',
            ]);

            $user = auth()->user();
            $businessId = $user->current_business_id;

            if (!$businessId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No business selected',
                ], 400);
            }

            $period = $request->input('period', 'month');

            // Generate report data
            $metricsEngine = new MetricsEngine($businessId, $period);
            $metrics = $metricsEngine->getMetrics();

            $intelligenceEngine = new IntelligenceEngine();
            $analysis = $intelligenceEngine->analyze($metrics);

            // Generate PDF
            $reportGenerator = new ReportGenerator();
            $pdf = $reportGenerator->generatePDF($metrics, $analysis);

            $filename = sprintf(
                'BI_Report_%s_%s.pdf',
                str_replace(' ', '_', $metrics['business']['name']),
                date('Y-m-d')
            );

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('BI PDF Export Failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available periods
     */
    public function getPeriods()
    {
        return response()->json([
            'periods' => [
                ['value' => 'today', 'label' => 'Today'],
                ['value' => 'week', 'label' => 'This Week'],
                ['value' => 'month', 'label' => 'This Month'],
                ['value' => 'quarter', 'label' => 'This Quarter'],
                ['value' => 'year', 'label' => 'This Year'],
                ['value' => 'all', 'label' => 'All Time'],
            ],
        ]);
    }
}
