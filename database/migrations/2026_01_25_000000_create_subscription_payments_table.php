<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id')->nullable()->index();
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->string('checkout_request_id')->nullable()->index();
            $table->string('merchant_request_id')->nullable()->index();
            $table->string('mpesa_receipt')->nullable()->index();
            $table->string('phone')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('status')->default('pending');
            $table->json('metadata')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscription_payments');
    }
};
