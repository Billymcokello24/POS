<?php

namespace App\Jobs;

use App\Services\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes for large reports
    public $tries = 2;
    public $backoff = 30;

    protected $businessId;
    protected $period;
    protected $cacheKey;

    /**
     * Create a new job instance.
     */
    public function __construct(int $businessId, string $period, string $cacheKey)
    {
        $this->businessId = $businessId;
        $this->period = $period;
        $this->cacheKey = $cacheKey;
    }

    /**
     * Execute the job.
     */
    public function handle(ReportService $reportService): void
    {
        try {
            Log::info('Generating report', [
                'business_id' => $this->businessId,
                'period' => $this->period,
            ]);

            $reportData = $reportService->generateBusinessReport($this->businessId, $this->period);

            // Cache the result for 1 hour
            Cache::put($this->cacheKey, $reportData, 3600);

            // Mark as complete
            Cache::put($this->cacheKey . '_status', 'complete', 3600);

            Log::info('Report generated successfully', [
                'business_id' => $this->businessId,
                'period' => $this->period,
            ]);
        } catch (\Exception $e) {
            Cache::put($this->cacheKey . '_status', 'failed', 3600);
            Cache::put($this->cacheKey . '_error', $e->getMessage(), 3600);

            Log::error('Failed to generate report', [
                'business_id' => $this->businessId,
                'period' => $this->period,
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
        Cache::put($this->cacheKey . '_status', 'failed', 3600);
        Cache::put($this->cacheKey . '_error', $exception->getMessage(), 3600);

        Log::error('Report generation job failed permanently', [
            'business_id' => $this->businessId,
            'period' => $this->period,
            'error' => $exception->getMessage(),
        ]);
    }
}
