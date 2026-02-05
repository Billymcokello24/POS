<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpired extends Notification implements ShouldQueue
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
        $billingCycle = $this->subscription->payment_details['billing_cycle'] ?? 'monthly';
        $billingLabel = $billingCycle === 'yearly' ? 'Annual' : 'Monthly';

        return (new MailMessage)
            ->error()
            ->subject('🔒 Subscription Expired - Workspace Access Limited')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your **' . $billingLabel . '** subscription for **' . $this->subscription->plan_name . '** has expired.')
            ->line('As a result, premium features have been temporarily locked.')
            ->line('**Subscription Details:**')
            ->line('Plan: ' . $this->subscription->plan_name)
            ->line('Billing Cycle: ' . $billingLabel)
            ->line('Expired On: ' . $this->subscription->ends_at->format('F j, Y'))
            ->line('Don\'t worry, **your data is safe!** Simply renew your subscription to restore full access immediately.')
            ->action('Restore Access Now', url('/subscription'))
            ->line('We look forward to having you back in full operation.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $billingCycle = $this->subscription->payment_details['billing_cycle'] ?? 'monthly';
        $billingLabel = $billingCycle === 'yearly' ? 'Annual' : 'Monthly';

        return [
            'type' => 'subscription.expired',
            'title' => 'Subscription Expired',
            'message' => "Your {$billingLabel} subscription for {$this->subscription->plan_name} has expired. Renew now to restore access.",
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan_name,
            'billing_cycle' => $billingCycle,
            'expired_at' => $this->subscription->ends_at->toIso8601String(),
            'icon' => '🔒',
        ];
    }
}
