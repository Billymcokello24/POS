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
        Schema::table('businesses', function (Blueprint $table) {
            if (!Schema::hasColumn('businesses', 'plan_ends_at')) {
                $table->timestamp('plan_ends_at')->nullable()->after('plan_id');
            }
            if (!Schema::hasColumn('businesses', 'subscription_updated_at')) {
                $table->timestamp('subscription_updated_at')->nullable()->after('plan_ends_at');
            }
            if (!Schema::hasColumn('businesses', 'active_features')) {
                $table->json('active_features')->nullable()->after('subscription_updated_at')->comment('Cached active feature slugs');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['subscription_updated_at', 'active_features']);
        });
    }
};
