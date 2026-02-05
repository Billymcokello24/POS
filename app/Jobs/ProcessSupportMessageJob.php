<?php

namespace App\Jobs;

use App\Models\SupportMessage;
use App\Models\User;
use App\Notifications\SupportMessageNotification;
use App\Events\SupportMessageSent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSupportMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;
    public $backoff = [5, 15, 30];

    protected $messageId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $messageId)
    {
        $this->messageId = $messageId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $message = SupportMessage::with(['ticket', 'user'])->find($this->messageId);

            if (!$message) {
                Log::warning('ProcessSupportMessageJob: Message not found', [
                    'message_id' => $this->messageId
                ]);
                return;
            }

            $ticket = $message->ticket;
            $sender = $message->user;

            if (!$ticket || !$sender) {
                Log::warning('ProcessSupportMessageJob: Ticket or sender not found', [
                    'message_id' => $this->messageId
                ]);
                return;
            }

            // Broadcast the message (async via queue)
            broadcast(new SupportMessageSent($message))->toOthers();

            // Notify the other party
            if ($message->is_from_admin) {
                // Admin sent message - notify the ticket owner
                $ticketOwner = User::find($ticket->user_id);

                if ($ticketOwner) {
                    $ticketOwner->notify(
                        new SupportMessageNotification($message, 'Support Admin', $ticket->id)
                    );

                    Log::info('ProcessSupportMessageJob: Ticket owner notified', [
                        'message_id' => $this->messageId,
                        'user_id' => $ticketOwner->id
                    ]);
                }
            } else {
                // Business user sent message - notify all super admins
                $superAdmins = User::where('is_super_admin', true)->get();

                foreach ($superAdmins as $admin) {
                    $admin->notify(
                        new SupportMessageNotification($message, $sender->name, $ticket->id)
                    );
                }

                Log::info('ProcessSupportMessageJob: Admins notified', [
                    'message_id' => $this->messageId,
                    'admin_count' => $superAdmins->count()
                ]);
            }

            // Update ticket's updated_at timestamp
            $ticket->touch();

            Log::info('ProcessSupportMessageJob completed successfully', [
                'message_id' => $this->messageId
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessSupportMessageJob failed', [
                'message_id' => $this->messageId,
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
        Log::error('ProcessSupportMessageJob failed permanently', [
            'message_id' => $this->messageId,
            'error' => $exception->getMessage(),
        ]);
    }
}
