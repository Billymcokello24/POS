<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MpesaPayment;
use App\Models\Subscription;
use App\Services\SubscriptionActivationService;
use Illuminate\Support\Facades\Log;

class ForceVerifySubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:force-verify 
                            {--dry-run : Show what would be done without making changes}
                            {--days= : Only check payments from the last N days (default: 30)}
                            {--all : Check all payments regardless of date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Force verify and activate any pending subscriptions that have successful payments.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(SubscriptionActivationService $activationService)
    {
        $dryRun = $this->option('dry-run');
        $days = $this->option('days') ?? 30;
        $all = $this->option('all');

        $this->info('╔════════════════════════════════════════════════════════════╗');
        $this->info('║   Force Subscription Verification & Activation Sweep      ║');
        $this->info('╚════════════════════════════════════════════════════════════╝');
        $this->newLine();

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Statistics
        $stats = [
            'total_payments_checked' => 0,
            'already_active' => 0,
            'activated' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        // 1. Find all successful MpesaPayments
        $this->info('📊 Scanning for successful M-Pesa payments...');
        
        $query = MpesaPayment::where(function($q) {
                $q->where('result_code', 0)
                  ->orWhere('status', 'success');
            });

        // Apply date filter
        if (!$all) {
            $query->where('created_at', '>=', now()->subDays($days));
            $this->info("   Filtering: Last {$days} days");
        } else {
            $this->info("   Filtering: All time");
        }

        $successfulPayments = $query->get();
        $stats['total_payments_checked'] = $successfulPayments->count();

        $this->info("   Found: {$stats['total_payments_checked']} successful payments");
        $this->newLine();

        if ($stats['total_payments_checked'] === 0) {
            $this->warn('No successful payments found to process.');
            return 0;
        }

        // 2. Process each payment
        $this->info('🔄 Processing payments...');
        $this->newLine();

        $progressBar = $this->output->createProgressBar($stats['total_payments_checked']);
        $progressBar->start();

        $details = [];

        foreach ($successfulPayments as $payment) {
            $progressBar->advance();
            
            // Check if this payment is already linked to an ACTIVE subscription
            if ($payment->subscription_id) {
                $sub = Subscription::find($payment->subscription_id);
                if ($sub && $sub->is_active && $sub->is_verified) {
                    $stats['already_active']++;
                    continue; // Already good
                }
            }

            // Check if payment has subscription metadata
            $metadata = $payment->metadata ?? [];
            $isSubscriptionPayment = ($metadata['type'] ?? '') === 'subscription';

            if (!$isSubscriptionPayment && !$payment->checkout_request_id) {
                $stats['skipped']++;
                continue; // Not a subscription payment
            }

            // Attempt activation via service
            if (!$dryRun) {
                try {
                    $success = $activationService->finalizeFromPayment([
                        'checkout_request_id' => $payment->checkout_request_id,
                        'mpesa_receipt' => $payment->receipt,
                        'result_code' => $payment->result_code ?? 0
                    ]);

                    if ($success) {
                        $stats['activated']++;
                        $details[] = [
                            'status' => '✅',
                            'checkout' => $payment->checkout_request_id,
                            'receipt' => $payment->receipt ?? 'N/A',
                            'amount' => 'KES ' . number_format($payment->amount, 2),
                            'business_id' => $payment->business_id,
                        ];
                    } else {
                        $stats['failed']++;
                        $details[] = [
                            'status' => '❌',
                            'checkout' => $payment->checkout_request_id,
                            'receipt' => $payment->receipt ?? 'N/A',
                            'amount' => 'KES ' . number_format($payment->amount, 2),
                            'business_id' => $payment->business_id,
                        ];
                    }
                } catch (\Exception $e) {
                    $stats['failed']++;
                    $details[] = [
                        'status' => '⚠️',
                        'checkout' => $payment->checkout_request_id,
                        'receipt' => $payment->receipt ?? 'N/A',
                        'amount' => 'KES ' . number_format($payment->amount, 2),
                        'business_id' => $payment->business_id,
                        'error' => substr($e->getMessage(), 0, 50),
                    ];
                    Log::error("Force Verify Command Error", [
                        'error' => $e->getMessage(), 
                        'checkout' => $payment->checkout_request_id
                    ]);
                }
            } else {
                // Dry run - just count what would be done
                $stats['activated']++;
                $details[] = [
                    'status' => '🔍',
                    'checkout' => $payment->checkout_request_id,
                    'receipt' => $payment->receipt ?? 'N/A',
                    'amount' => 'KES ' . number_format($payment->amount, 2),
                    'business_id' => $payment->business_id,
                ];
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // 3. Display results
        $this->info('╔════════════════════════════════════════════════════════════╗');
        $this->info('║                      RESULTS SUMMARY                       ║');
        $this->info('╚════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Payments Checked', $stats['total_payments_checked']],
                ['Already Active/Verified', $stats['already_active']],
                ['Successfully Activated', $stats['activated']],
                ['Failed to Activate', $stats['failed']],
                ['Skipped (Not Subscription)', $stats['skipped']],
            ]
        );

        // 4. Display details if any activations occurred
        if (!empty($details) && count($details) <= 20) {
            $this->newLine();
            $this->info('📋 Detailed Results:');
            $this->newLine();
            
            $headers = ['Status', 'Checkout ID', 'Receipt', 'Amount', 'Business ID'];
            $rows = array_map(function($detail) {
                return [
                    $detail['status'],
                    substr($detail['checkout'], 0, 20) . '...',
                    $detail['receipt'],
                    $detail['amount'],
                    $detail['business_id'],
                ];
            }, $details);
            
            $this->table($headers, $rows);
        } elseif (count($details) > 20) {
            $this->info("📋 {$stats['activated']} subscriptions processed (too many to display)");
        }

        $this->newLine();
        
        if ($dryRun) {
            $this->warn('🔍 DRY RUN COMPLETE - No actual changes were made');
            $this->info('   Run without --dry-run to apply changes');
        } else {
            $this->info('✅ Sweep complete!');
        }

        return 0;
    }
}
