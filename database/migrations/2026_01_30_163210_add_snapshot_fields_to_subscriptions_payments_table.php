<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions_payments', function (Blueprint $table) {
            // Add plan_id first if it doesn't exist
            if (!Schema::hasColumn('subscriptions_payments', 'plan_id')) {
                 $table->unsignedBigInteger('plan_id')->nullable()->after('business_id');
            }
            
            // Now add the rest
            $table->string('business_name')->nullable()->after('business_id');
            $table->string('plan_name')->nullable()->after('plan_id');
            $table->string('billing_cycle')->nullable()->after('amount');
            $table->timestamp('plan_start_date')->nullable()->after('billing_cycle');
            $table->timestamp('plan_end_date')->nullable()->after('plan_start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions_payments', function (Blueprint $table) {
            $table->dropColumn(['business_name', 'plan_id', 'plan_name', 'billing_cycle', 'plan_start_date', 'plan_end_date']);
        });
    }
};
