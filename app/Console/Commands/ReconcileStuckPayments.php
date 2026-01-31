<?php

namespace App\Console\Commands;

use App\Models\MpesaPayment;
use App\Models\Business;
use App\Services\MpesaService;
use App\Services\SubscriptionActivationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileStuckPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:reconcile-stuck';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile pending M-Pesa payments that have stayed in pending state for too long';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Saas Billing: Checking for stuck pending payments...");

        // Stuck threshold: Pending for more than 5 minutes
        $stuckPayments = MpesaPayment::where('status', MpesaPayment::STATUS_PENDING)
            ->whereNull('result_code')
            ->whereNotNull('checkout_request_id')
            ->where('created_at', '<', now()->subMinutes(5))
            ->where('created_at', '>', now()->subHours(24)) // Safaricom only keeps records for 24h
            ->get();

        if ($stuckPayments->isEmpty()) {
            $this->comment("No stuck pending payments found.");
            return;
        }

        $this->info("Found {$stuckPayments->count()} stuck payments. Attempting reconciliation...");

        $activationService = app(SubscriptionActivationService::class);

        foreach ($stuckPayments as $payment) {
            $this->reconcile($payment, $activationService);
        }

        $this->info("Reconciliation complete.");
    }

    protected function reconcile(MpesaPayment $payment, SubscriptionActivationService $service)
    {
        $business = $payment->business;
        if (!$business) {
            $this->error("No business associated with payment #{$payment->id}. Skipping.");
            return;
        }

        try {
            $mpesaService = new MpesaService($business);
            $result = $mpesaService->queryStkStatus($payment->checkout_request_id);

            $resultCode = $result['ResultCode'] ?? null;
            
            if ($resultCode === null) {
                // Still pending or error querying
                $this->comment("Payment #{$payment->id} (Checkout: {$payment->checkout_request_id}) is still pending on Safaricom.");
                return;
            }

            $this->info("Payment #{$payment->id} reached final state (Code: {$resultCode}). Updating ledger.");

            // Update the ledger (triggers observer for activation if success)
            $payment->update([
                'result_code' => $resultCode,
                'receipt' => $result['MpesaReceiptNumber'] ?? null,
                'raw_response' => array_merge($payment->raw_response ?? [], ['query_result' => $result]),
            ]);

            // If it failed, ensure we tell the service to cleanup
            if ((int)$resultCode !== 0) {
                $service->finalizeFromPayment([
                    'checkout_request_id' => $payment->checkout_request_id,
                    'result_code' => $resultCode
                ]);
            }

            Log::info("Saas Billing: Stuck payment #{$payment->id} reconciled.", ['result_code' => $resultCode]);

        } catch (\Throwable $e) {
            $this->error("Failed to reconcile payment #{$payment->id}: {$e->getMessage()}");
            Log::error("Saas Billing: Reconciliation failed for #{$payment->id}", ['error' => $e->getMessage()]);
        }
    }
}
