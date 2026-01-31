<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiring extends Notification
{
    use Queueable;

    protected $subscription;

    /**
     * Create a new notification instance.
     */
    public function __construct($subscription)
    {
        $this->subscription = $subscription;
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
            ->subject('Action Required: Your Subscription is Expiring Soon! ⚠️')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your subscription for **' . $this->subscription->plan_name . '** is set to expire in **' . $this->subscription->ends_at->diffInDays(now()) . ' days** on ' . $this->subscription->ends_at->format('F j, Y') . '.')
            ->line('To avoid any interruption to your retail operations, please renew your subscription before then.')
            ->action('Renew Subscription', url('/subscription'))
            ->line('Maintaining an active subscription ensures you keep access to all your data and premium features.');
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
