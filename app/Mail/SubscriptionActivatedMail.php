<?php

namespace App\Mail;

use App\Models\Subscription;
use App\Services\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubscriptionActivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subscription;
    public $billingCycle;
    public $billingLabel;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
        $this->billingCycle = $subscription->payment_details['billing_cycle'] ?? 'monthly';
        $this->billingLabel = $this->billingCycle === 'yearly' ? 'Annual' : 'Monthly';
    }

    public function build()
    {
        $pdfService = app(PdfService::class);
        $pdf = $pdfService->generateReceipt($this->subscription);
        $receiptNumber = 'RCP-' . str_pad($this->subscription->id, 6, '0', STR_PAD_LEFT);

        return $this->subject('✅ Subscription Activated: ' . $this->subscription->plan_name . '! 🚀')
            ->markdown('emails.subscription-activated')
            ->attachData($pdf->output(), "{$receiptNumber}.pdf", [
                'mime' => 'application/pdf',
            ]);
    }
}
