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
            $table->boolean('is_active')->default(false)->after('status');
            $table->boolean('is_verified')->default(false)->after('is_active');
            $table->string('checkout_request_id')->nullable()->after('transaction_id')->index();
            // verified_at, activated_at already exist in current schema view
        });

        Schema::table('mpesa_payments', function (Blueprint $table) {
             $table->unsignedBigInteger('subscription_id')->nullable()->after('business_id');
             $table->integer('result_code')->nullable()->after('amount');
             // receipt usually maps to existing column 'receipt' or 'mpesa_receipt'
             // MpesaPayment model has 'receipt' in fillable.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'is_verified']);
        });

        Schema::table('mpesa_payments', function (Blueprint $table) {
            $table->dropColumn(['subscription_id', 'result_code']);
        });
    }
};
