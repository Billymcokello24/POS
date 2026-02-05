<?php

namespace App\Notifications;

use App\Services\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    protected $subscription;

    /**
     * Create a new notification instance.
     */
    public function __construct($subscription)
    {
        $this->subscription = $subscription;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $billingCycle = $this->subscription->payment_details['billing_cycle'] ?? 'monthly';
        $billingLabel = $billingCycle === 'yearly' ? 'Annual' : 'Monthly';
        $daysRemaining = $this->subscription->ends_at->diffInDays(now());

        $mail = (new MailMessage)
            ->subject('⚠️ Action Required: Your Subscription Expires in ' . $daysRemaining . ' Days')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your **' . $billingLabel . '** subscription for **' . $this->subscription->plan_name . '** is set to expire in **' . $daysRemaining . ' days**.')
            ->line('**Subscription Details:**')
            ->line('Plan: ' . $this->subscription->plan_name)
            ->line('Billing Cycle: ' . $billingLabel)
            ->line('Expiry Date: ' . $this->subscription->ends_at->format('F j, Y'))
            ->line('Amount: ' . ($this->subscription->currency ?? 'KES') . ' ' . number_format($this->subscription->amount, 2))
            ->line('To avoid any interruption to your retail operations, please renew your subscription before then.')
            ->action('Renew Subscription', url('/subscription'))
            ->line('Maintaining an active subscription ensures you keep access to all your data and premium features.')
            ->line('A renewal invoice has been attached for your reference.');

        // Attach invoice PDF
        try {
            $pdfService = app(PdfService::class);
            $pdf = $pdfService->generateInvoice($this->subscription, $daysRemaining);
            $invoiceNumber = 'INV-' . str_pad($this->subscription->id, 6, '0', STR_PAD_LEFT) . '-R';

            // Save to temporary storage
            $tempPath = storage_path("app/temp/{$invoiceNumber}.pdf");

            // Ensure temp directory exists
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            file_put_contents($tempPath, $pdf->output());

            // Attach the file
            $mail->attach($tempPath, [
                'as' => "{$invoiceNumber}.pdf",
                'mime' => 'application/pdf',
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to attach invoice PDF: ' . $e->getMessage(), [
                'subscription_id' => $this->subscription->id,
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $mail;
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
        $daysRemaining = $this->subscription->ends_at->diffInDays(now());

        return [
            'type' => 'subscription.expiring',
            'title' => 'Subscription Expiring Soon',
            'message' => "Your {$billingLabel} subscription for {$this->subscription->plan_name} expires in {$daysRemaining} days. Renew now to avoid interruption.",
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan_name,
            'billing_cycle' => $billingCycle,
            'days_remaining' => $daysRemaining,
            'expires_at' => $this->subscription->ends_at->toIso8601String(),
            'icon' => '⚠️',
        ];
    }
}
