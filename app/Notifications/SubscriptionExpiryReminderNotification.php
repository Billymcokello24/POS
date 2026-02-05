<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Services\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiryReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $subscription;
    protected $daysUntilExpiry;

    public function __construct(Subscription $subscription, int $daysUntilExpiry)
    {
        $this->subscription = $subscription;
        $this->daysUntilExpiry = $daysUntilExpiry;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = match($this->daysUntilExpiry) {
            0 => "Your subscription expires today!",
            1 => "Your subscription expires tomorrow!",
            default => "Your subscription expires in {$this->daysUntilExpiry} days"
        };

        $billingCycle = $this->subscription->payment_details['billing_cycle'] ?? 'monthly';
        $billingLabel = $billingCycle === 'yearly' ? 'Annual' : 'Monthly';

        $mailMessage = (new MailMessage)
            ->subject('⏰ Subscription Expiring Soon - Action Required')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line($message)
            ->line('Your **' . $billingLabel . '** subscription (' . $this->subscription->plan_name . ') will expire on **' . $this->subscription->ends_at->format('F j, Y') . '**.')
            ->line('To avoid service interruption, please renew your subscription as soon as possible.')
            ->line('**Plan:** ' . $this->subscription->plan_name)
            ->line('**Billing Cycle:** ' . $billingLabel)
            ->line('**Renewal Amount:** ' . number_format($this->subscription->amount, 2) . ' ' . ($this->subscription->currency ?? 'KES'))
            ->action('Renew Now', url('/subscription'))
            ->line('A renewal invoice has been attached for your reference.');

        // Attach invoice PDF
        try {
            $pdfService = app(PdfService::class);
            $pdf = $pdfService->generateInvoice($this->subscription, $this->daysUntilExpiry);
            $invoiceNumber = 'INV-' . str_pad($this->subscription->id, 6, '0', STR_PAD_LEFT) . '-R';

            // Save to temporary storage
            $tempPath = storage_path("app/temp/{$invoiceNumber}.pdf");

            // Ensure temp directory exists
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            file_put_contents($tempPath, $pdf->output());

            // Attach the file
            $mailMessage->attach($tempPath, [
                'as' => "{$invoiceNumber}.pdf",
                'mime' => 'application/pdf',
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to attach invoice PDF: ' . $e->getMessage(), [
                'subscription_id' => $this->subscription->id,
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $mailMessage;
    }

    public function toArray(object $notifiable): array
    {
        $message = match($this->daysUntilExpiry) {
            0 => "Your subscription expires today!",
            1 => "Your subscription expires tomorrow!",
            default => "Your subscription expires in {$this->daysUntilExpiry} days"
        };

        $billingCycle = $this->subscription->payment_details['billing_cycle'] ?? 'monthly';

        return [
            'type' => 'subscription.expiring',
            'title' => 'Subscription Expiring Soon',
            'message' => $message,
            'business_id' => $this->subscription->business_id,
            'subscription_id' => $this->subscription->id,
            'days_until_expiry' => $this->daysUntilExpiry,
            'billing_cycle' => $billingCycle,
            'icon' => '⏰',
        ];
    }
}

