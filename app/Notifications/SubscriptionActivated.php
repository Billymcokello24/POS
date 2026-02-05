<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Services\PdfService;

class SubscriptionActivated extends Notification
{
    use Queueable;

    public $subscription;

    public function __construct($subscription)
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

        $pdfService = app(PdfService::class);
        $pdf = $pdfService->generateReceipt($this->subscription);
        $receiptNumber = 'RCP-' . str_pad($this->subscription->id, 6, '0', STR_PAD_LEFT);

        return (new MailMessage)
            ->subject('✅ Subscription Activated: ' . $this->subscription->plan_name . '! 🚀')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your payment was successful and your subscription has been activated.')
            ->line('**Plan Details:**')
            ->line('Plan: ' . $this->subscription->plan_name)
            ->line('Billing Cycle: **' . $billingLabel . '**')
            ->line('Amount: ' . $this->subscription->currency . ' ' . number_format($this->subscription->amount, 2))
            ->line('Receipt/Transaction: ' . ($this->subscription->mpesa_receipt ?? $this->subscription->transaction_id))
            ->line('Starts: ' . $this->subscription->starts_at->format('F j, Y'))
            ->line('Expires: ' . $this->subscription->ends_at->format('F j, Y') . ' (' . $billingLabel . ' renewal)')
            ->action('Launch Workplace', url('/dashboard'))
            ->line('Your retail workspace is now fully operational with all premium features unlocked.')
            ->line('A payment receipt has been attached for your records.')
            ->attachData($pdf->output(), "{$receiptNumber}.pdf", [
                'mime' => 'application/pdf',
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $billingCycle = $this->subscription->payment_details['billing_cycle'] ?? 'monthly';
        $billingLabel = $billingCycle === 'yearly' ? 'Annual' : 'Monthly';

        return [
            'type' => 'subscription.activated',
            'title' => 'Subscription Activated',
            'message' => "Your {$this->subscription->plan_name} subscription ({$billingLabel}) has been activated successfully",
            'plan_name' => $this->subscription->plan_name,
            'billing_cycle' => $billingCycle,
            'amount' => $this->subscription->amount,
            'currency' => $this->subscription->currency,
            'expires_at' => $this->subscription->ends_at->toIso8601String(),
            'icon' => '✅',
        ];
    }
}
