<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessRegistered extends Notification
{
    use Queueable;

    protected $business;

    /**
     * Create a new notification instance.
     */
    public function __construct($business)
    {
        $this->business = $business;
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
            ->subject('Welcome to ModernPOS - Workspace Ready! 🎉')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Congratulations! Your business, **' . $this->business->name . '**, has been successfully provisioned on the ModernPOS Enterprise Suite.')
            ->line('You now have full access to manage your inventory, process sales, and track your business growth in real-time.')
            ->action('Go to Dashboard', url('/dashboard'))
            ->line('Thank you for choosing ModernPOS for your retail operations!');
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
