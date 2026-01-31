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
            $table->string('approval_status')->default('pending')->after('status'); // pending, approved, rejected
            $table->timestamp('approved_at')->nullable()->after('approval_status');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
            $table->text('rejection_reason')->nullable()->after('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions_payments', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'approved_at', 'approved_by', 'rejection_reason']);
        });
    }
};
