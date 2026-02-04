<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DuplicateSubscriptionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $subscription;
    protected $duplicateCount;

    public function __construct(Subscription $subscription, int $duplicateCount)
    {
        $this->subscription = $subscription;
        $this->duplicateCount = $duplicateCount;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription.duplicate',
            'title' => 'Duplicate Subscription Alert',
            'message' => "Business {$this->subscription->business->name} has {$this->duplicateCount} duplicate active subscriptions",
            'business_id' => $this->subscription->business_id,
            'subscription_id' => $this->subscription->id,
            'duplicate_count' => $this->duplicateCount,
            'icon' => '⚠️',
        ];
    }
}

