<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessRestored extends Notification implements ShouldQueue
{
    use Queueable;

    protected $business;
    protected $username;
    protected $password;

    /**
     * Create a new notification instance.
     */
    public function __construct($business, $username = null, $password = null)
    {
        $this->business = $business;
        $this->username = $username;
        $this->password = $password;
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
            ->success()
            ->subject('Account Restored: Welcome Back to ' . $this->business->name . ' ✅')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Great news! Your business account **' . $this->business->name . '** has been restored and reactivated.')
            ->line('You can now log in and access all your workspace features as usual.');

        if ($this->username && $this->password) {
            $mail->line('**Your Temporary Credentials:**')
                ->line('Username: ' . $this->username)
                ->line('Password: ' . $this->password)
                ->line('*(You will be asked to change this password upon login)*');
        }

        return $mail
            ->action('Log In to Dashboard', url('/login'))
            ->line('Thank you for your patience. We are glad to have you back in operation!');
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
        ];
    }
}
