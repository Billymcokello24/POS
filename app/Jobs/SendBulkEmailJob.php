<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\GenericBulkEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBulkEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;
    public $backoff = [10, 30, 60];

    protected $user;
    protected $subject;
    protected $content;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, string $subject, string $content)
    {
        $this->user = $user;
        $this->subject = $subject;
        $this->content = $content;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->user->notify(new GenericBulkEmail($this->subject, $this->content));

            Log::info('Bulk email sent successfully', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'subject' => $this->subject,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send bulk email', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
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
        Log::error('Bulk email job failed permanently', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'subject' => $this->subject,
            'error' => $exception->getMessage(),
        ]);
    }
}
