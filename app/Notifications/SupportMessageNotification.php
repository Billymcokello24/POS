<?php

namespace App\Notifications;

use App\Models\SupportMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SupportMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $message;
    protected $senderName;
    protected $ticketId;

    /**
     * Create a new notification instance.
     */
    public function __construct(SupportMessage $message, string $senderName, int $ticketId)
    {
        $this->message = $message;
        $this->senderName = $senderName;
        $this->ticketId = $ticketId;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => "New message from {$this->senderName}: " . substr($this->message->message, 0, 50),
            'ticket_id' => $this->ticketId,
            'message_id' => $this->message->id,
        ];
    }
}

