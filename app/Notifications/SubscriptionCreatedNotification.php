<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SubscriptionCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription.created',
            'title' => 'New Subscription',
            'message' => "Business {$this->subscription->business->name} has subscribed to {$this->subscription->plan->name}",
            'business_id' => $this->subscription->business_id,
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan->name,
            'icon' => '📋',
        ];
    }
}

