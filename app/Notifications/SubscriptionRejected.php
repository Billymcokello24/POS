<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRejected extends Notification implements ShouldQueue
{
    use Queueable;

    protected $subscription;
    protected $reason;

    public function __construct($subscription, string $reason = '')
    {
        $this->subscription = $subscription;
        $this->reason = $reason;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $billingCycle = $this->subscription->payment_details['billing_cycle'] ?? 'monthly';
        $billingLabel = $billingCycle === 'yearly' ? 'Annual' : 'Monthly';

        return (new MailMessage)
            ->error()
            ->subject('❌ Subscription Request Rejected')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('We regret to inform you that your subscription request has been rejected.')
            ->line('**Subscription Details:**')
            ->line('Plan: **' . ($this->subscription->plan_name ?? 'the selected plan') . '**')
            ->line('Billing Cycle: ' . $billingLabel)
            ->line('Amount: ' . ($this->subscription->currency ?? 'KES') . ' ' . number_format($this->subscription->amount ?? 0, 2))
            ->when(!empty($this->reason), function (MailMessage $message) {
                return $message->line('**Reason for Rejection:** ' . $this->reason);
            })
            ->line('If you believe this was a mistake or need assistance, please contact our support team immediately.')
            ->action('Contact Support', url('/contact'))
            ->line('We apologize for any inconvenience caused.');
    }

    public function toArray(object $notifiable): array
    {
        $billingCycle = $this->subscription->payment_details['billing_cycle'] ?? 'monthly';
        $billingLabel = $billingCycle === 'yearly' ? 'Annual' : 'Monthly';

        return [
            'type' => 'subscription.rejected',
            'title' => 'Subscription Rejected',
            'message' => 'Your ' . $billingLabel . ' subscription request has been rejected. ' . ($this->reason ? 'Reason: ' . $this->reason : ''),
            'subscription_id' => $this->subscription->id ?? null,
            'plan_name' => $this->subscription->plan_name ?? null,
            'billing_cycle' => $billingCycle,
            'reason' => $this->reason,
            'icon' => '❌',
        ];
    }
}
