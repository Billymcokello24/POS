<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\PdfService;
use App\Models\Subscription;

class SubscriptionCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subscription;
    public $recipient;
    public $billingCycle;
    public $billingLabel;

    /**
     * Create a new message instance.
     */
    public function __construct(Subscription $subscription, $recipient)
    {
        $this->subscription = $subscription;
        $this->recipient = $recipient;
        $this->billingCycle = $subscription->payment_details['billing_cycle'] ?? 'monthly';
        $this->billingLabel = $this->billingCycle === 'yearly' ? 'Annual' : 'Monthly';
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $subject = '✅ New Subscription Created - Payment Confirmed';

        $mail = $this->subject($subject)
            ->markdown('emails.subscription-created');

        // Attach receipt PDF if payment is confirmed and PdfService is available
        try {
            if (!empty($this->subscription->mpesa_receipt)) {
                $pdfService = app(PdfService::class);
                if ($pdfService) {
                    $pdf = $pdfService->generateReceipt($this->subscription);
                    if ($pdf && method_exists($pdf, 'output')) {
                        $receiptNumber = 'RCP-' . str_pad($this->subscription->id, 6, '0', STR_PAD_LEFT);
                        $mail->attachData($pdf->output(), "{$receiptNumber}.pdf", [
                            'mime' => 'application/pdf',
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to attach receipt PDF: ' . $e->getMessage());
        }

        return $mail;
    }
}
