<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Services\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
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
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $billingCycle = $this->subscription->payment_details['billing_cycle'] ?? 'monthly';
        $billingLabel = $billingCycle === 'yearly' ? 'Annual' : 'Monthly';

        $message = (new MailMessage)
            ->subject('⬇️ Subscription Plan Changed')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line("Your subscription plan has been changed.")
            ->line("**Previous Plan:** {$this->oldPlanName}")
            ->line("**New Plan:** {$this->subscription->plan->name}")
            ->line("**Billing Cycle:** {$billingLabel}")
            ->line("**Amount:** " . number_format($this->subscription->amount, 2) . " {$this->subscription->currency}")
            ->line("**Valid Until:** " . ($this->subscription->ends_at ? $this->subscription->ends_at->format('F j, Y') : 'N/A'))
            ->line('If you have any questions, please contact support.')
            ->action('View Subscription', url('/subscription'))
            ->line('Thank you for using our platform!');

        // Attach receipt PDF
        if ($this->subscription->status === 'active' && $this->subscription->mpesa_receipt) {
            try {
                $pdfService = app(PdfService::class);
                $pdf = $pdfService->generateReceipt($this->subscription);
                $receiptNumber = 'RCP-' . str_pad($this->subscription->id, 6, '0', STR_PAD_LEFT);

                // Save to temporary storage
                $tempPath = storage_path("app/temp/{$receiptNumber}_Plan_Change.pdf");

                // Ensure temp directory exists
                if (!file_exists(storage_path('app/temp'))) {
                    mkdir(storage_path('app/temp'), 0755, true);
                }

                file_put_contents($tempPath, $pdf->output());

                // Attach the file
                $message->attach($tempPath, [
                    'as' => "{$receiptNumber}_Plan_Change.pdf",
                    'mime' => 'application/pdf',
                ]);

            } catch (\Exception $e) {
                \Log::error('Failed to attach receipt PDF: ' . $e->getMessage(), [
                    'subscription_id' => $this->subscription->id,
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        $billingCycle = $this->subscription->payment_details['billing_cycle'] ?? 'monthly';
        $billingLabel = $billingCycle === 'yearly' ? 'Annual' : 'Monthly';

        return [
            'type' => 'subscription.downgraded',
            'title' => 'Subscription Plan Changed',
            'message' => "Business {$this->subscription->business->name} changed from {$this->oldPlanName} to {$this->subscription->plan->name} ({$billingLabel})",
            'business_id' => $this->subscription->business_id,
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan->name,
            'old_plan_name' => $this->oldPlanName,
            'billing_cycle' => $billingCycle,
            'icon' => '⬇️',
        ];
    }
}

