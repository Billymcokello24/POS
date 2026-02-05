<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImpersonationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $adminName;
    protected $action;

    public function __construct(string $adminName, string $action = 'started')
    {
        $this->adminName = $adminName;
        $this->action = $action;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isStarted = $this->action === 'started';

        if ($isStarted) {
            return (new MailMessage)
                ->subject('🔐 Security Alert: Impersonation Started')
                ->greeting('Hello ' . $notifiable->name . ',')
                ->line("Admin {$this->adminName} has started impersonating your account.")
                ->line('This action is being logged for security purposes.')
                ->line('If you did not expect this, please contact support immediately.')
                ->action('Contact Support', url('/contact'));
        }

        return (new MailMessage)
            ->subject('🔓 Impersonation Session Ended')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line("The impersonation session by {$this->adminName} has ended.")
            ->line('Your account is now fully under your control.');
    }

    public function toArray(object $notifiable): array
    {
        $isStarted = $this->action === 'started';

        return [
            'type' => $isStarted ? 'impersonation.started' : 'impersonation.ended',
            'title' => $isStarted ? 'Impersonation Started' : 'Impersonation Ended',
            'message' => $isStarted
                ? "Admin {$this->adminName} has started impersonating your account"
                : "Impersonation session by {$this->adminName} has ended",
            'admin_name' => $this->adminName,
            'action' => $this->action,
            'icon' => $isStarted ? '🔐' : '🔓',
        ];
    }
}

