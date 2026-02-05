<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImportFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $type;
    protected $error;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $type, string $error)
    {
        $this->type = $type;
        $this->error = $error;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject("❌ {$this->type} Import Failed")
            ->greeting("Hello {$notifiable->name}!")
            ->line("Unfortunately, your {$this->type} import has failed.")
            ->line("Error: {$this->error}")
            ->line('Please check your import file and try again.')
            ->action('Go to Dashboard', url('/dashboard'));
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'import_failed',
            'title' => "❌ {$this->type} Import Failed",
            'message' => $this->error,
            'error' => $this->error,
        ];
    }
}
