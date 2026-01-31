<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserAccountCreated extends Notification
{
    use Queueable;

    protected $password;
    protected $businessName;

    /**
     * Create a new notification instance.
     */
    public function __construct($password, $businessName)
    {
        $this->password = $password;
        $this->businessName = $businessName;
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
            ->subject('Welcome to the ' . $this->businessName . ' Team! 🏢')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('You have been added to the **' . $this->businessName . '** management team on ModernPOS.')
            ->line('Below are your login credentials to get started:')
            ->line('**Email:** ' . $notifiable->email)
            ->line('**Temporary Password:** ' . $this->password)
            ->action('Login to Workspace', url('/login'))
            ->line('For security reasons, we recommend changing your password after your first login.')
            ->line('Welcome aboard!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
