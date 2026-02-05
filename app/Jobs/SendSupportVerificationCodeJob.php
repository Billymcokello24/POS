<?php

namespace App\Jobs;

use App\Models\Business;
use App\Notifications\SupportVerificationCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendSupportVerificationCodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;
    public $backoff = [5, 15, 30];

    protected $businessId;
    protected $email;
    protected $code;

    /**
     * Create a new job instance.
     */
    public function __construct(int $businessId, string $email, string $code)
    {
        $this->businessId = $businessId;
        $this->email = $email;
        $this->code = $code;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Send verification code email
            Notification::route('mail', $this->email)
                ->notify(new SupportVerificationCode($this->code));

            Log::info('SendSupportVerificationCodeJob: Verification code sent', [
                'business_id' => $this->businessId,
                'email' => $this->email
            ]);

        } catch (\Exception $e) {
            Log::error('SendSupportVerificationCodeJob failed', [
                'business_id' => $this->businessId,
                'email' => $this->email,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Rethrow to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendSupportVerificationCodeJob failed permanently', [
            'business_id' => $this->businessId,
            'email' => $this->email,
            'error' => $exception->getMessage(),
        ]);
    }
}
