<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Notifications\SubscriptionExpiring;
use App\Notifications\SubscriptionExpired;

class CheckSubscriptionExpiries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-expiries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan for expired subscriptions and update SaaS and Business state';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = now();
        $this->info("Saas Billing: Processing expiries and renewals at {$now->toDateTimeString()}...");

        // 1. Process Actual Expiries
        $expiredSubscriptions = Subscription::where('status', 'active')
            ->where('is_active', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now)
            ->get();

        foreach ($expiredSubscriptions as $subscription) {
            $this->processExpiry($subscription);
        }

        // 2. Process Renewals (Subscriptions expiring in the next 24 hours)
        $renewableSubscriptions = Subscription::where('status', 'active')
            ->where('is_active', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', $now)
            ->where('ends_at', '<=', $now->copy()->addHours(24))
            ->get();

        foreach ($renewableSubscriptions as $subscription) {
            $this->processRenewalTrigger($subscription);
        }

        // 3. Process Reminders (Expiring in exactly 3 days)
        $reminderSubscriptions = Subscription::where('status', 'active')
            ->where('is_active', true)
            ->whereNotNull('ends_at')
            ->whereDate('ends_at', $now->copy()->addDays(3)->toDateString())
            ->get();

        foreach ($reminderSubscriptions as $subscription) {
            $this->processReminder($subscription);
        }

        $this->info("Billing check complete.");
    }

    protected function processExpiry(Subscription $subscription)
    {
        try {
            \DB::transaction(function () use ($subscription) {
                $now = now();
                
                // Transition Subscription state (Truth)
                $subscription->update([
                    'status' => Subscription::STATUS_EXPIRED,
                    'is_active' => false,
                    'metadata' => array_merge($subscription->metadata ?? [], [
                        'expired_at' => $now->toDateTimeString(),
                        'reason' => 'auto_check'
                    ])
                ]);

                // Downgrade Business to Basic Plan
                $business = $subscription->business;
                if ($business) {
                    $basicPlan = \App\Models\Plan::where('price_monthly', 0)
                        ->orWhere('name', 'like', '%Basic%')
                        ->first();

                    $business->update([
                        'plan_id' => $basicPlan?->id ?? 1,
                        'plan_ends_at' => null,
                        'active_features' => [] // Reset cache
                    ]);
                    
                    if (method_exists($business, 'refreshFeatures')) {
                        $business->refreshFeatures();
                    }
                }

                try { \App\Services\SseService::pushBusinessEvent($subscription->business_id, 'subscription.expired', ['id' => $subscription->id]); } catch (\Throwable $_) {}
                
                // Notify Admins
                $this->notifyAdmins($subscription, new SubscriptionExpired($subscription));

                Log::info("Saas Billing: Subscription #{$subscription->id} expired. Business #{$subscription->business_id} downgraded.");
            });
        } catch (\Throwable $e) {
            Log::error("Saas Billing: Expiry failed for #{$subscription->id}", ['error' => $e->getMessage()]);
        }
    }

    protected function processRenewalTrigger(Subscription $subscription)
    {
        // Avoid double-triggering renewal if one is already pending or recently attempted
        $lastRenewalAttempt = $subscription->metadata['last_renewal_attempt'] ?? null;
        if ($lastRenewalAttempt && now()->diffInHours(now()->parse($lastRenewalAttempt)) < 12) {
            return;
        }

        try {
            Log::info("Saas Billing: Triggering renewal window for Subscription #{$subscription->id}");
            
            $phone = $subscription->payment_details['phone'] ?? null;
            if ($phone && ($subscription->metadata['auto_renew'] ?? false)) {
                $this->initiateAutoRenewal($subscription, $phone);
            } else {
                // Otherwise notify via event
                try {
                    \App\Services\SseService::pushBusinessEvent($subscription->business_id, 'subscription.nearing_expiry', [
                        'id' => $subscription->id,
                        'ends_at' => $subscription->ends_at?->toDateTimeString()
                    ]);
                } catch (\Throwable $_) {}
            }

            $subscription->update([
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'last_renewal_attempt' => now()->toDateTimeString()
                ])
            ]);

        } catch (\Throwable $e) {
            Log::error("Saas Billing: Renewal trigger failed for #{$subscription->id}", ['error' => $e->getMessage()]);
        }
    }

    protected function initiateAutoRenewal(Subscription $subscription, $phone)
    {
        try {
            Log::info("Saas Billing: Initiating auto-renew STK for Business #{$subscription->business_id}");
            
            // We can't directly call the controller easily without Auth
            // But we can manually trigger the payment service or use the activation service strategy
            // For now, we'll use a mocked request to the controller if possible, or just log
            // In a real production system, this would call a dedicated PaymentService method
            
            $finalizer = app(\App\Http\Controllers\Api\SubscriptionPaymentController::class);
            
            // We need to bypass Auth or provide a system user
            // For now, let's just log exactly what would happen
            Log::info("Saas Billing: Auto-Renewal Request", [
                'plan_id' => $subscription->plan_id,
                'phone' => $phone,
                'amount' => $subscription->amount
            ]);
        } catch (\Throwable $e) {
            Log::error("Saas Billing: Auto-initiation failed", ['error' => $e->getMessage()]);
        }
    }

    protected function notifyUserOfExpiry(Subscription $subscription)
    {
        try {
            \App\Services\SseService::pushBusinessEvent($subscription->business_id, 'subscription.nearing_expiry', [
                'id' => $subscription->id,
                'ends_at' => $subscription->ends_at?->toDateTimeString()
            ]);
        } catch (\Throwable $_) {}
    }

    protected function processReminder(Subscription $subscription)
    {
        // Avoid double-notifying for the same day
        $lastReminder = $subscription->metadata['last_expiry_reminder_3d'] ?? null;
        if ($lastReminder && $lastReminder === now()->toDateString()) {
            return;
        }

        Log::info("Saas Billing: Sending 3-day reminder for Subscription #{$subscription->id}");
        
        $this->notifyAdmins($subscription, new SubscriptionExpiring($subscription));

        $subscription->update([
            'metadata' => array_merge($subscription->metadata ?? [], [
                'last_expiry_reminder_3d' => now()->toDateString()
            ])
        ]);
    }

    protected function notifyAdmins(Subscription $subscription, $notification)
    {
        try {
            $business = $subscription->business;
            if ($business) {
                $admins = $business->users()->whereHas('roles', function($q) {
                    $q->where('name', 'admin');
                })->get();
                
                foreach ($admins as $admin) {
                    $admin->notify($notification);
                }
            }
        } catch (\Throwable $e) {
            Log::error("Saas Billing: Failed to send notification for Subscription #{$subscription->id}", ['error' => $e->getMessage()]);
        }
    }
}
