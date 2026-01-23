<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop global barcode unique index if it exists (default name is products_barcode_unique)
            try {
                $table->dropUnique('products_barcode_unique');
            } catch (\Throwable $e) {
                // ignore if it doesn't exist
            }

            // Add composite unique for business_id + barcode with an explicit name
            try {
                $table->unique(['business_id', 'barcode'], 'products_business_id_barcode_unique');
            } catch (\Throwable $e) {
                // ignore if already exists
            }

            // Ensure composite unique for business_id + sku exists (explicit name)
            try {
                $table->dropUnique('products_business_id_sku_unique');
            } catch (\Throwable $e) {
                // ignore
            }
            try {
                $table->unique(['business_id', 'sku'], 'products_business_id_sku_unique');
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            try {
                $table->dropUnique(['business_id', 'barcode']);
            } catch (\Throwable $e) {
            }

            // restore global unique barcode (if desired)
            try {
                $table->unique('barcode');
            } catch (\Throwable $e) {
            }

            try {
                $table->dropUnique(['business_id', 'sku']);
            } catch (\Throwable $e) {
            }

            try {
                $table->unique('sku');
            } catch (\Throwable $e) {
            }
        });
    }
};
