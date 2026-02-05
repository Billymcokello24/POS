<?php
}
    }
        return $mail;

        }
            }
                \Log::error('Failed to attach receipt PDF: ' . $e->getMessage());
            } catch (\Exception $e) {
                ]);
                    'mime' => 'application/pdf',
                $mail->attachData($pdf->output(), "{$receiptNumber}.pdf", [

                $receiptNumber = 'RCP-' . str_pad($this->subscription->id, 6, '0', STR_PAD_LEFT);
                $pdf = $pdfService->generateReceipt($this->subscription);
                $pdfService = app(PdfService::class);
            try {
        if ($this->subscription->mpesa_receipt) {
        // Only attach receipt if payment is confirmed

            ->markdown('emails.subscription-created');
        $mail = $this->subject('✅ New Subscription Created - Payment Confirmed')
    {
    public function build()

    }
        $this->billingLabel = $this->billingCycle === 'yearly' ? 'Annual' : 'Monthly';
        $this->billingCycle = $subscription->payment_details['billing_cycle'] ?? 'monthly';
        $this->recipient = $recipient;
        $this->subscription = $subscription;
    {
    public function __construct(Subscription $subscription, $recipient)

    public $recipient;
    public $billingLabel;
    public $billingCycle;
    public $subscription;

    use Queueable, SerializesModels;
{
class SubscriptionCreatedMail extends Mailable

use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailable;
use Illuminate\Bus\Queueable;
use App\Services\PdfService;
use App\Models\Subscription;

namespace App\Mail;

