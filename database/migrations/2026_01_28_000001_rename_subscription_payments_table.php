<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // If legacy table exists and the new one doesn't, rename it so model and migrations align.
        if (Schema::hasTable('subscription_payments') && ! Schema::hasTable('subscriptions_payments')) {
            Schema::rename('subscription_payments', 'subscriptions_payments');
        }
    }

    public function down()
    {
        // revert rename if needed
        if (Schema::hasTable('subscriptions_payments') && ! Schema::hasTable('subscription_payments')) {
            Schema::rename('subscriptions_payments', 'subscription_payments');
        }
    }
};

