<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorSubscriptions extends Command
{
    protected $signature = 'subscriptions:monitor';

    protected $description = 'Monitor and deactivate expired subscriptions';

    public function handle()
    {
        $expired = Subscription::where('status', 'active')
            ->where('ends_at', '<', now())
            ->get();

        foreach ($expired as $subscription) {
            $subscription->update(['status' => 'expired']);

            // Optionally notify business.
            // For now, just logging.
            Log::info("Subscription #{$subscription->id} for Business #{$subscription->business_id} has expired.");

            // Note: Features are already restricted because HandleInertiaRequests and middleware
            // check for ACTIVE subscriptions/plans.
            // However, we should also nullify the plan_id if we want total restriction.
            // But usually subscription history is kept.
        }

        $this->info("Processed {$expired->count()} expired subscriptions.");
    }
}
