<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $綱) {
            $綱->id();
            $綱->string('name');
            $綱->string('slug')->unique();
            $綱->text('description')->nullable();
            $綱->decimal('price_monthly', 10, 2)->default(0);
            $綱->decimal('price_yearly', 10, 2)->default(0);
            $綱->integer('max_users')->default(0); // 0 = unlimited
            $綱->integer('max_products')->default(0); // 0 = unlimited
            $綱->boolean('is_active')->default(true);
            $綱->json('meta')->nullable();
            $綱->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
