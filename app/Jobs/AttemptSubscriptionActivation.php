<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AttemptSubscriptionActivation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $subscriptionId;
    public ?string $receipt;
    public array $options;

    // declare retry/backoff properties explicitly
    public int $tries = 3;
    public int|array $backoff = 5;

    public function __construct(int $subscriptionId, ?string $receipt = null, array $options = [])
    {
        $this->subscriptionId = $subscriptionId;
        $this->receipt = $receipt;
        $this->options = $options;

        // allow override via options
        if (isset($options['tries']) && is_int($options['tries'])) $this->tries = $options['tries'];
        if (isset($options['backoff'])) $this->backoff = $options['backoff'];
    }

    public function handle()
    {
        try {
            $subscription = \App\Models\Subscription::find($this->subscriptionId);
            if (! $subscription) {
                Log::warning('AttemptSubscriptionActivation: subscription not found', ['subscription_id' => $this->subscriptionId]);
                return;
            }

            if ($subscription->status === 'active') {
                Log::debug('AttemptSubscriptionActivation: subscription already active', ['subscription_id' => $subscription->id]);
                return;
            }

            // ensure plan exists
            $plan = $subscription->plan ?? ( $subscription->plan_id ? \App\Models\Plan::find($subscription->plan_id) : null );
            if (! $plan && ! empty($subscription->plan_name)) {
                $plan = \App\Models\Plan::where('name', 'like', $subscription->plan_name)->first();
            }
            if (! $plan) {
                Log::warning('AttemptSubscriptionActivation: plan not found, skipping activation', ['subscription_id' => $subscription->id, 'plan_id' => $subscription->plan_id, 'plan_name' => $subscription->plan_name]);
                return;
            }

            // persist receipt to transaction_id if provided
            if (! empty($this->receipt)) {
                $subscription->transaction_id = $this->receipt;
                // also set mpesa_stk for admin label
                $subscription->mpesa_stk = $this->receipt;
            }

            // attach activated metadata
            $pd = $subscription->payment_details ?? [];
            $pd = is_array($pd) ? $pd : (is_object($pd) ? (array)$pd : []);
            if (! empty($this->receipt)) $pd['mpesa_receipt'] = $this->receipt;
            $pd['auto_reconcile'] = true;
            $pd['activated_at'] = now()->toDateTimeString();
            $subscription->payment_details = $pd;
            $subscription->save();

            if (method_exists($subscription->business, 'activateSubscription')) {
                $subscription->business->activateSubscription($subscription, $this->receipt, array_merge(['auto_reconcile' => true, 'source' => 'attempt_job'], $this->options));
                try { \App\Services\SseService::pushBusinessEvent($subscription->business_id, 'subscription.activated', ['id' => $subscription->id, 'status' => 'active']); } catch (\Throwable $_) {}
                Log::info('AttemptSubscriptionActivation: subscription activated', ['subscription_id' => $subscription->id, 'receipt' => $this->receipt]);
            } else {
                Log::warning('AttemptSubscriptionActivation: business activation helper missing', ['subscription_id' => $subscription->id]);
            }
        } catch (\Throwable $e) {
            Log::error('AttemptSubscriptionActivation failed', ['subscription_id' => $this->subscriptionId, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e; // let the queue handle retries
        }
    }
}
