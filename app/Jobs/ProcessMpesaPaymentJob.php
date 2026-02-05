<?php

namespace App\Jobs;

use App\Models\MpesaPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMpesaPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;
    public $backoff = [10, 30, 60];

    protected $paymentId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $paymentId)
    {
        $this->paymentId = $paymentId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $payment = MpesaPayment::find($this->paymentId);

            if (!$payment) {
                Log::warning('ProcessMpesaPaymentJob: Payment not found', [
                    'payment_id' => $this->paymentId
                ]);
                return;
            }

            // Only process successful payments
            if ($payment->result_code !== 0 || $payment->status !== 'success') {
                Log::info('ProcessMpesaPaymentJob: Payment not successful, skipping', [
                    'payment_id' => $this->paymentId,
                    'status' => $payment->status
                ]);
                return;
            }

            // Process subscription activation
            if (!$payment->subscription_id) {
                try {
                    app(\App\Services\SubscriptionActivationService::class)->finalizeFromPayment([
                        'checkout_request_id' => $payment->checkout_request_id,
                        'mpesa_receipt' => $payment->receipt,
                        'result_code' => $payment->result_code,
                        'phone' => $payment->phone,
                        'amount' => $payment->amount,
                    ]);

                    $payment->refresh();

                    Log::info('ProcessMpesaPaymentJob: Subscription activated', [
                        'payment_id' => $this->paymentId,
                        'subscription_id' => $payment->subscription_id
                    ]);
                } catch (\Exception $e) {
                    Log::error('ProcessMpesaPaymentJob: Subscription activation failed', [
                        'payment_id' => $this->paymentId,
                        'error' => $e->getMessage()
                    ]);
                    throw $e;
                }
            }

            // Process sale payment if linked
            if ($payment->sale_id) {
                try {
                    $sale = \App\Models\Sale::find($payment->sale_id);

                    if ($sale && $sale->status === 'pending') {
                        $sale->update([
                            'status' => 'completed',
                            'payment_method' => 'MPESA',
                            'payment_reference' => $payment->receipt,
                            'paid_at' => now()
                        ]);

                        Log::info('ProcessMpesaPaymentJob: Sale completed', [
                            'payment_id' => $this->paymentId,
                            'sale_id' => $payment->sale_id
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('ProcessMpesaPaymentJob: Sale completion failed', [
                        'payment_id' => $this->paymentId,
                        'sale_id' => $payment->sale_id,
                        'error' => $e->getMessage()
                    ]);
                    // Don't throw - sale completion is secondary
                }
            }

            Log::info('ProcessMpesaPaymentJob completed successfully', [
                'payment_id' => $this->paymentId
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessMpesaPaymentJob failed', [
                'payment_id' => $this->paymentId,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Rethrow to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessMpesaPaymentJob failed permanently', [
            'payment_id' => $this->paymentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
