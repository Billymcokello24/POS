<?php

namespace App\Notifications;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AutoSuspensionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $business;
    protected $reason;

    public function __construct(Business $business, string $reason = 'Subscription expired')
    {
        $this->business = $business;
        $this->reason = $reason;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('🚫 Business Auto-Suspended')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line("Business '{$this->business->name}' has been automatically suspended.")
            ->line("Reason: {$this->reason}")
            ->line('Please take action to restore access.')
            ->action('View Details', url('/admin/businesses'))
            ->line('This requires immediate attention.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'business.auto_suspended',
            'title' => 'Business Auto-Suspended',
            'message' => "Business '{$this->business->name}' was automatically suspended: {$this->reason}",
            'business_id' => $this->business->id,
            'business_name' => $this->business->name,
            'reason' => $this->reason,
            'icon' => '🚫',
        ];
    }
}

