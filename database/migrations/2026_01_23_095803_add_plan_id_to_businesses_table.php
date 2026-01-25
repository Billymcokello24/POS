<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $綱) {
            $綱->foreignId('plan_id')->nullable()->constrained('plans')->onDelete('set null');
            $綱->string('suspension_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $綱) {
            $綱->dropForeign(['plan_id']);
            $綱->dropColumn(['plan_id', 'suspension_reason']);
        });
    }
};
