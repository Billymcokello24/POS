<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Services\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCreatedNotification extends Notification
{
    use Queueable;

    public $subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
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
            ->subject('✅ New Subscription Created - Payment Confirmed')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line("Great news! A new subscription has been created for **{$this->subscription->business->name}**.")
            ->line("**Plan:** {$this->subscription->plan_name}")
            ->line("**Billing Cycle:** {$billingLabel}")
            ->line("**Amount:** " . number_format($this->subscription->amount, 2) . " {$this->subscription->currency}")
            ->line("**Valid From:** " . ($this->subscription->starts_at ? $this->subscription->starts_at->format('F j, Y') : 'N/A'))
            ->line("**Valid Until:** " . ($this->subscription->ends_at ? $this->subscription->ends_at->format('F j, Y') : 'N/A'))
            ->action('View Subscription', url('/admin/subscriptions'))
            ->line('A payment receipt has been attached to this email for your records.');

        // Only attach receipt PDF if subscription has been paid
        if ($this->subscription->mpesa_receipt) {
            $pdfService = app(PdfService::class);
            $pdf = $pdfService->generateReceipt($this->subscription);
            $receiptNumber = 'RCP-' . str_pad($this->subscription->id, 6, '0', STR_PAD_LEFT);

            $message->attachData($pdf->output(), "{$receiptNumber}.pdf", [
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
            'type' => 'subscription.created',
            'title' => 'New Subscription',
            'message' => "Business {$this->subscription->business->name} has subscribed to {$this->subscription->plan_name} ({$billingLabel})",
            'business_id' => $this->subscription->business_id,
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan_name,
            'billing_cycle' => $billingCycle,
            'icon' => '📋',
        ];
    }
}

