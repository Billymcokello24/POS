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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Check if columns don't already exist before adding
            if (!Schema::hasColumn('subscriptions', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('activated_at');
            }
            if (!Schema::hasColumn('subscriptions', 'verified_by')) {
                $table->string('verified_by')->nullable()->after('verified_at')->comment('system or admin_id');
            }
            if (!Schema::hasColumn('subscriptions', 'payment_confirmed_at')) {
                $table->timestamp('payment_confirmed_at')->nullable()->after('verified_by');
            }
            
            // Add index for faster queries on verified subscriptions
            $table->index(['status', 'verified_at'], 'idx_subscriptions_status_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_subscriptions_status_verified');
            $table->dropColumn(['verified_at', 'verified_by', 'payment_confirmed_at']);
        });
    }
};
