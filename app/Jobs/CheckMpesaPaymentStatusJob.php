<?php

namespace App\Jobs;

use App\Models\MpesaPayment;
use App\Services\MpesaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckMpesaPaymentStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;
    public $backoff = [5, 15, 30];

    protected $checkoutRequestId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $checkoutRequestId)
    {
        $this->checkoutRequestId = $checkoutRequestId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $payment = MpesaPayment::where('checkout_request_id', $this->checkoutRequestId)->first();

            if (!$payment) {
                Log::warning('CheckMpesaPaymentStatusJob: Payment not found', [
                    'checkout_request_id' => $this->checkoutRequestId
                ]);
                return;
            }

            // Only query if still pending
            if ($payment->status !== 'pending') {
                return;
            }

            $business = $payment->business;
            if (!$business) {
                Log::warning('CheckMpesaPaymentStatusJob: No business linked to payment', [
                    'payment_id' => $payment->id
                ]);
                return;
            }

            $mpesaService = new MpesaService($business);
            $response = $mpesaService->queryStkStatus($payment->checkout_request_id);

            // Update payment status based on query response
            if (isset($response['ResultCode'])) {
                $payment->update([
                    'result_code' => $response['ResultCode'],
                    'status' => MpesaPayment::resolveStatusFromCode($response['ResultCode']),
                    'raw_response' => array_merge($payment->raw_response ?? [], ['query_resp' => $response]),
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'source_update' => 'background_status_check',
                        'checked_at' => now()->toDateTimeString()
                    ])
                ]);

                Log::info('CheckMpesaPaymentStatusJob: Payment status updated', [
                    'checkout_request_id' => $this->checkoutRequestId,
                    'result_code' => $response['ResultCode'],
                    'status' => $payment->status
                ]);

                // If successful, trigger subscription activation
                if ($payment->result_code === 0 && !$payment->subscription_id) {
                    app(\App\Services\SubscriptionActivationService::class)->finalizeFromPayment([
                        'checkout_request_id' => $payment->checkout_request_id,
                        'mpesa_receipt' => $payment->receipt,
                        'result_code' => $payment->result_code
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('CheckMpesaPaymentStatusJob failed', [
                'checkout_request_id' => $this->checkoutRequestId,
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
        Log::error('CheckMpesaPaymentStatusJob failed permanently', [
            'checkout_request_id' => $this->checkoutRequestId,
            'error' => $exception->getMessage(),
        ]);
    }
}
