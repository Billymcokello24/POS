<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // This ensures business.plan_id is always correct for existing businesses
        $businesses = \App\Models\Business::all();

        foreach ($businesses as $business) {
            try {
                $latestSubscription = $business->subscriptions()
                    ->whereIn('status', ['active', 'trialing'])
                    ->latest('starts_at')
                    ->first();

                if ($latestSubscription && $business->plan_id !== $latestSubscription->plan_id) {
                    $business->plan_id = $latestSubscription->plan_id;
                    $business->save();
                }
            } catch (\Throwable $e) {
                // ignore per-business failures
            }
        }
    }

    public function down()
    {
        // Intentionally left blank - we don't revert data changes
    }
};

