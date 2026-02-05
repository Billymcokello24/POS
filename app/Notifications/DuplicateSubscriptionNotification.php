<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
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
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('⚠️ Duplicate Subscription Alert')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line("Business {$this->subscription->business->name} has {$this->duplicateCount} duplicate active subscriptions.")
            ->line('Please review and resolve this to avoid billing issues.')
            ->action('View Subscriptions', url('/admin/subscriptions'))
            ->line('This requires your attention.');
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

