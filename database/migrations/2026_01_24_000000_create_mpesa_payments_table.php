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
        Schema::create('mpesa_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('sale_id')->nullable()->index();
            $table->string('checkout_request_id')->nullable()->index();
            $table->string('merchant_request_id')->nullable();
            $table->string('receipt')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('reference')->nullable();
            $table->string('account_reference')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->json('metadata')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            // optionally add foreign key constraint if businesses table exists
            // $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mpesa_payments');
    }
};

