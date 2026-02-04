<?php
}
    }
        ];
            'icon' => '⬆️',
            'old_plan_name' => $this->oldPlanName,
            'plan_name' => $this->subscription->plan->name,
            'subscription_id' => $this->subscription->id,
            'business_id' => $this->subscription->business_id,
            'message' => "Business {$this->subscription->business->name} upgraded from {$this->oldPlanName} to {$this->subscription->plan->name}",
            'title' => 'Subscription Upgraded',
            'type' => 'subscription.upgraded',
        return [
    {
    public function toArray(object $notifiable): array

    }
        return ['database', 'broadcast'];
    {
    public function via(object $notifiable): array

    }
        $this->oldPlanName = $oldPlanName;
        $this->subscription = $subscription;
    {
    public function __construct(Subscription $subscription, string $oldPlanName)

    protected $oldPlanName;
    protected $subscription;

    use Queueable;
{
class SubscriptionUpgradedNotification extends Notification implements ShouldQueue

use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use App\Models\Subscription;

namespace App\Notifications;


