<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('content')->nullable();
            $table->timestamps();
        });

        // Seed default welcome page
        DB::table('pages')->insert([
            'key' => 'welcome',
            'content' => json_encode([
                'hero_title' => 'POWER YOUR <br> COMMERCE',
                'hero_subtitle' => 'The complete retail operating system. Manage inventory, track sales, and process transactions with enterprise-grade speed and reliability.',
                'hero_bg_image' => 'https://images.unsplash.com/photo-1497366811353-6870744d04b2?q=80&w=2069&auto=format&fit=crop',
                'announcement_text' => 'System Status: Optimal',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};

