<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessSuspended extends Notification implements ShouldQueue
{
    use Queueable;

    protected $business;
    protected $reason;

    /**
     * Create a new notification instance.
     */
    public function __construct($business, $reason = null)
    {
        $this->business = $business;
        $this->reason = $reason;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->error()
            ->subject('Account Suspended: ' . $this->business->name . ' ⚠️')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('We are writing to inform you that your business account **' . $this->business->name . '** has been suspended by the system administrator.');

        if ($this->reason) {
            $mail->line('**Reason for suspension:** ' . $this->reason);
        }

        return $mail
            ->line('During suspension, you will not be able to log in or access your workspace features.')
            ->line('If you believe this is a mistake or need assistance in resolving this, please contact our support team immediately.')
            ->line('We hope to resolve this with you soon.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'business_id' => $this->business->id,
            'reason' => $this->reason,
        ];
    }
}
