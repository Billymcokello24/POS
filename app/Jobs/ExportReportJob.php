<?php

namespace App\Jobs;

use App\Services\ReportService;
use App\Services\PDFReportService;
use App\Services\ExcelReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ReportReadyNotification;
use App\Models\User;

class ExportReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes for large exports
    public $tries = 2;
    public $backoff = 60;

    protected $businessId;
    protected $period;
    protected $format;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $businessId, string $period, string $format, int $userId)
    {
        $this->businessId = $businessId;
        $this->period = $period;
        $this->format = $format;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(
        ReportService $reportService,
        PDFReportService $pdfReportService,
        ExcelReportService $excelReportService
    ): void {
        try {
            Log::info('Exporting report', [
                'business_id' => $this->businessId,
                'period' => $this->period,
                'format' => $this->format,
            ]);

            // Generate report data
            $reportData = $reportService->generateBusinessReport($this->businessId, $this->period);

            // Generate filename
            $businessName = str_replace(' ', '_', $reportData['business']['name']);
            $date = date('Y-m-d-His');
            $filename = "{$businessName}_Report_{$this->period}_{$date}.{$this->format}";

            // Generate file based on format
            if ($this->format === 'pdf') {
                $pdf = $pdfReportService->generatePDF($reportData);
                $content = $pdf->output();
            } elseif (in_array($this->format, ['xlsx', 'excel'])) {
                $spreadsheet = $excelReportService->generateExcel($reportData);
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

                ob_start();
                $writer->save('php://output');
                $content = ob_get_clean();
            } else {
                throw new \Exception("Unsupported export format: {$this->format}");
            }

            // Store file
            $path = "exports/reports/{$filename}";
            Storage::disk('local')->put($path, $content);

            // Notify user
            $user = User::find($this->userId);
            if ($user) {
                $user->notify(new ReportReadyNotification($filename, $path));
            }

            Log::info('Report exported successfully', [
                'business_id' => $this->businessId,
                'period' => $this->period,
                'format' => $this->format,
                'filename' => $filename,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to export report', [
                'business_id' => $this->businessId,
                'period' => $this->period,
                'format' => $this->format,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Report export job failed permanently', [
            'business_id' => $this->businessId,
            'period' => $this->period,
            'format' => $this->format,
            'error' => $exception->getMessage(),
        ]);
    }
}
