<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Services\PDFReportService;
use App\Services\ExcelReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessReportController extends Controller
{
    protected $reportService;
    protected $pdfReportService;
    protected $excelReportService;

    public function __construct(
        ReportService $reportService,
        PDFReportService $pdfReportService,
        ExcelReportService $excelReportService
    ) {
        $this->reportService = $reportService;
        $this->pdfReportService = $pdfReportService;
        $this->excelReportService = $excelReportService;
    }

    /**
     * Generate business report (JSON response)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generate(Request $request)
    {
        try {
            $request->validate([
                'period' => 'required|in:today,week,month,year,all',
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

            $reportData = $this->reportService->generateBusinessReport($businessId, $period);

            return response()->json([
                'success' => true,
                'data' => $reportData,
            ]);

        } catch (\Exception $e) {
            Log::error('Business Report Generation Failed', [
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
     * Export report as PDF
     *
     * @param Request $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function exportPDF(Request $request)
    {
        try {
            $request->validate([
                'period' => 'required|in:today,week,month,year,all',
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
            $reportData = $this->reportService->generateBusinessReport($businessId, $period);

            // Generate PDF
            $pdf = $this->pdfReportService->generatePDF($reportData);

            // Generate filename
            $filename = $this->generateFilename($reportData, 'pdf');

            // Return PDF download
            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('PDF Export Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export report as Excel
     *
     * @param Request $request
     * @return StreamedResponse|\Illuminate\Http\JsonResponse
     */
    public function exportExcel(Request $request)
    {
        try {
            $request->validate([
                'period' => 'required|in:today,week,month,year,all',
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
            $reportData = $this->reportService->generateBusinessReport($businessId, $period);

            // Generate Excel
            $spreadsheet = $this->excelReportService->generateExcel($reportData);

            // Generate filename
            $filename = $this->generateFilename($reportData, 'xlsx');

            // Return Excel download
            $writer = new Xlsx($spreadsheet);

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);

        } catch (\Exception $e) {
            Log::error('Excel Export Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export Excel: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview report (returns HTML for frontend display)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function preview(Request $request)
    {
        try {
            $request->validate([
                'period' => 'required|in:today,week,month,year,all',
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

            $reportData = $this->reportService->generateBusinessReport($businessId, $period);

            // Return structured data for frontend rendering
            return response()->json([
                'success' => true,
                'data' => $reportData,
                'meta' => [
                    'generated_at' => now()->toIso8601String(),
                    'period' => $period,
                    'business_id' => $businessId,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Report Preview Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to preview report: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available report periods
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPeriods()
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['value' => 'today', 'label' => 'Today'],
                ['value' => 'week', 'label' => 'This Week'],
                ['value' => 'month', 'label' => 'This Month'],
                ['value' => 'year', 'label' => 'This Year'],
                ['value' => 'all', 'label' => 'All Time'],
            ],
        ]);
    }

    /**
     * Generate filename for export
     */
    protected function generateFilename(array $reportData, string $extension): string
    {
        $businessName = str_replace(' ', '_', $reportData['business']['name']);
        $period = $reportData['reporting_period']['period'];
        $date = date('Y-m-d');

        return "{$businessName}_Business_Report_{$period}_{$date}.{$extension}";
    }
}
