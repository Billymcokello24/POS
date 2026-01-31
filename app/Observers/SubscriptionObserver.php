<?php

namespace App\Observers;

use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class SubscriptionObserver
{
    public function created(Subscription $subscription)
    {
        // When a new active subscription is created, update the business
        if (in_array($subscription->status, ['active', 'trialing'])) {
            $this->updateBusinessPlan($subscription);
        }
    }

    public function updated(Subscription $subscription)
    {
        // When subscription status changes to active, update business
        if ($subscription->isDirty('status') &&
            in_array($subscription->status, ['active', 'trialing'])) {
            $this->updateBusinessPlan($subscription);
        }
    }

    private function updateBusinessPlan(Subscription $subscription)
    {
        $business = $subscription->business;

        if ($business && $business->plan_id !== $subscription->plan_id) {
            Log::info('SubscriptionObserver: Updating business plan_id', [
                'business_id' => $business->id,
                'old_plan_id' => $business->plan_id,
                'new_plan_id' => $subscription->plan_id,
                'subscription_id' => $subscription->id
            ]);

            $business->plan_id = $subscription->plan_id;
            $business->save();
        }
    }
}

