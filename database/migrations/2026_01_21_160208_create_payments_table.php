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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method'); // CASH, CARD, MPESA, BANK_TRANSFER
            $table->decimal('amount', 10, 2);
            $table->string('reference_number')->nullable(); // For card/mpesa transactions
            $table->string('status')->default('completed'); // completed, pending, failed
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // Store additional payment info
            $table->timestamps();

            $table->index(['sale_id']);
            $table->index(['payment_method', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
