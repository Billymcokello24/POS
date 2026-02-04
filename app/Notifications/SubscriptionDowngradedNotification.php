<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SubscriptionDowngradedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $subscription;
    protected $oldPlanName;

    public function __construct(Subscription $subscription, string $oldPlanName)
    {
        $this->subscription = $subscription;
        $this->oldPlanName = $oldPlanName;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription.downgraded',
            'title' => 'Subscription Downgraded',
            'message' => "Business {$this->subscription->business->name} downgraded from {$this->oldPlanName} to {$this->subscription->plan->name}",
            'business_id' => $this->subscription->business_id,
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan->name,
            'old_plan_name' => $this->oldPlanName,
            'icon' => '⬇️',
        ];
    }
}

