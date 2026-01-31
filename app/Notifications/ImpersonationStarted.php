<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImpersonationStarted extends Notification implements ShouldQueue
{
    use Queueable;

    protected $reason;
    protected $adminName;

    /**
     * Create a new notification instance.
     */
    public function __construct($reason, $adminName)
    {
        $this->reason = $reason;
        $this->adminName = $adminName;
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
        return (new MailMessage)
            ->subject('Security Alert: Account Impersonation Started')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('This is a formal notification that a SuperAdmin (' . $this->adminName . ') has started an impersonation session on your account.')
            ->line('Reason provided: ' . ($this->reason ?: 'No reason provided.'))
            ->line('This action is being logged for security and auditing purposes.')
            ->line('If you did not expect this or have concerns, please contact our support team immediately.')
            ->action('Contact Support', url('/contact'))
            ->line('Thank you for using our platform.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'reason' => $this->reason,
            'admin_name' => $this->adminName,
        ];
    }
}
