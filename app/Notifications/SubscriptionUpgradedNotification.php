<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Services\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionUpgradedNotification extends Notification
{
    use Queueable;

    public $subscription;
    public $oldPlanName;

    public function __construct(Subscription $subscription, string $oldPlanName)
    {
        $this->subscription = $subscription;
        $this->oldPlanName = $oldPlanName;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $billingCycle = $this->subscription->payment_details['billing_cycle'] ?? 'monthly';
        $billingLabel = $billingCycle === 'yearly' ? 'Annual' : 'Monthly';

        $message = (new MailMessage)
            ->subject('⬆️ Subscription Upgraded Successfully!')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line("Great news! Your subscription has been upgraded.")
            ->line("**Previous Plan:** {$this->oldPlanName}")
            ->line("**New Plan:** {$this->subscription->plan->name}")
            ->line("**Billing Cycle:** {$billingLabel}")
            ->line("**Amount:** " . number_format($this->subscription->amount, 2) . " {$this->subscription->currency}")
            ->line("**Valid Until:** " . ($this->subscription->ends_at ? $this->subscription->ends_at->format('F j, Y') : 'N/A'))
            ->line('Enjoy your enhanced features!')
            ->action('View Dashboard', url('/dashboard'))
            ->line('Thank you for growing with us!');

        // Attach receipt PDF if payment is confirmed
        if ($this->subscription->status === 'active' && $this->subscription->mpesa_receipt) {
            $pdfService = app(PdfService::class);
            $pdf = $pdfService->generateReceipt($this->subscription);
            $receiptNumber = 'RCP-' . str_pad($this->subscription->id, 6, '0', STR_PAD_LEFT);

            $message->attachData($pdf->output(), "{$receiptNumber}_Upgrade.pdf", [
                'mime' => 'application/pdf',
            ]);
        }

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        $billingCycle = $this->subscription->payment_details['billing_cycle'] ?? 'monthly';
        $billingLabel = $billingCycle === 'yearly' ? 'Annual' : 'Monthly';

        return [
            'type' => 'subscription.upgraded',
            'title' => 'Subscription Upgraded',
            'message' => "Business {$this->subscription->business->name} upgraded from {$this->oldPlanName} to {$this->subscription->plan->name} ({$billingLabel})",
            'business_id' => $this->subscription->business_id,
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan->name,
            'old_plan_name' => $this->oldPlanName,
            'billing_cycle' => $billingCycle,
            'icon' => '⬆️',
        ];
    }
}
