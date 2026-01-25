<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $綱) {
            $綱->id();
            $綱->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $綱->string('event');
            $綱->text('description');
            $綱->json('metadata')->nullable();
            $綱->string('ip_address')->nullable();
            $綱->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
