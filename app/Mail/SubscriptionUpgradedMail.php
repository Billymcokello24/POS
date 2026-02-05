<?php

namespace App\Mail;

use App\Models\Subscription;
use App\Services\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubscriptionUpgradedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subscription;
    public $oldPlanName;
    public $billingCycle;
    public $billingLabel;

    public function __construct(Subscription $subscription, string $oldPlanName)
    {
        $this->subscription = $subscription;
        $this->oldPlanName = $oldPlanName;
        $this->billingCycle = $subscription->payment_details['billing_cycle'] ?? 'monthly';
        $this->billingLabel = $this->billingCycle === 'yearly' ? 'Annual' : 'Monthly';
    }

    public function build()
    {
        $mail = $this->subject('⬆️ Subscription Upgraded Successfully!')
            ->markdown('emails.subscription-upgraded');

        // Attach receipt if payment is confirmed
        if ($this->subscription->status === 'active' && $this->subscription->mpesa_receipt) {
            try {
                $pdfService = app(PdfService::class);
                $pdf = $pdfService->generateReceipt($this->subscription);
                $receiptNumber = 'RCP-' . str_pad($this->subscription->id, 6, '0', STR_PAD_LEFT);

                $mail->attachData($pdf->output(), "{$receiptNumber}_Upgrade.pdf", [
                    'mime' => 'application/pdf',
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to attach receipt PDF: ' . $e->getMessage());
            }
        }

        return $mail;
    }
}
