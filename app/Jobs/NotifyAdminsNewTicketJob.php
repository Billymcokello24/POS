<?php

namespace App\Jobs;

use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\NewSupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotifyAdminsNewTicketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;
    public $backoff = [5, 15, 30];

    protected $ticketId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $ticketId)
    {
        $this->ticketId = $ticketId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $ticket = SupportTicket::find($this->ticketId);

            if (!$ticket) {
                Log::warning('NotifyAdminsNewTicketJob: Ticket not found', [
                    'ticket_id' => $this->ticketId
                ]);
                return;
            }

            // Get all super admins
            $superAdmins = User::where('is_super_admin', true)->get();

            if ($superAdmins->isEmpty()) {
                Log::warning('NotifyAdminsNewTicketJob: No super admins found');
                return;
            }

            // Send notification to all super admins (queued automatically via ShouldQueue)
            Notification::send($superAdmins, new NewSupportTicket($ticket));

            Log::info('NotifyAdminsNewTicketJob: Admins notified successfully', [
                'ticket_id' => $this->ticketId,
                'admin_count' => $superAdmins->count()
            ]);

        } catch (\Exception $e) {
            Log::error('NotifyAdminsNewTicketJob failed', [
                'ticket_id' => $this->ticketId,
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
        Log::error('NotifyAdminsNewTicketJob failed permanently', [
            'ticket_id' => $this->ticketId,
            'error' => $exception->getMessage(),
        ]);
    }
}
