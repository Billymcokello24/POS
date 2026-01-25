<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_plan', function (Blueprint $綱) {
            $綱->id();
            $綱->foreignId('feature_id')->constrained()->onDelete('cascade');
            $綱->foreignId('plan_id')->constrained()->onDelete('cascade');
            $綱->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_plan');
    }
};
