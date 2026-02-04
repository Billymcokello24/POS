<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionActivated extends Notification
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
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Subscription Activated: ' . $this->subscription->plan_name . '! 🚀')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your payment was successful and your subscription has been activated.')
            ->line('**Plan Details:**')
            ->line('Plan: ' . $this->subscription->plan_name)
            ->line('Amount: ' . $this->subscription->currency . ' ' . number_format($this->subscription->amount, 2))
            ->line('Receipt/Transaction: ' . ($this->subscription->mpesa_receipt ?? $this->subscription->transaction_id))
            ->line('Expires On: ' . $this->subscription->ends_at->format('F j, Y'))
            ->action('Launch Workplace', url('/dashboard'))
            ->line('Your retail workspace is now fully operational with all premium features unlocked.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription.activated',
            'title' => 'Subscription Activated',
            'message' => "Your {$this->subscription->plan_name} subscription has been activated successfully",
            'plan_name' => $this->subscription->plan_name,
            'amount' => $this->subscription->amount,
            'currency' => $this->subscription->currency,
            'expires_at' => $this->subscription->ends_at->toIso8601String(),
            'icon' => '✅',
        ];
    }
}
