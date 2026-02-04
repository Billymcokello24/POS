<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SubscriptionExpiryReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $subscription;
    protected $daysUntilExpiry;

    public function __construct(Subscription $subscription, int $daysUntilExpiry)
    {
        $this->subscription = $subscription;
        $this->daysUntilExpiry = $daysUntilExpiry;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $message = match($this->daysUntilExpiry) {
            0 => "Your subscription expires today!",
            1 => "Your subscription expires tomorrow!",
            default => "Your subscription expires in {$this->daysUntilExpiry} days"
        };

        return [
            'type' => 'subscription.expiring',
            'title' => 'Subscription Expiring Soon',
            'message' => $message,
            'business_id' => $this->subscription->business_id,
            'subscription_id' => $this->subscription->id,
            'days_until_expiry' => $this->daysUntilExpiry,
            'icon' => '⏰',
        ];
    }
}

