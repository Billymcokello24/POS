<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Make sale_id nullable on payments and mpesa_payments to allow creating records
     * for subscription/m-pesa flows that don't have an associated sale.
     *
     * Note: This uses the Schema::table()->change() API which requires doctrine/dbal
     * to be installed. If you don't have it, run: composer require doctrine/dbal
     */
    public function up()
    {
        // payments table
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (Schema::hasColumn('payments', 'sale_id')) {
                    $table->unsignedBigInteger('sale_id')->nullable()->change();
                }
            });
        }

        // mpesa_payments table
        if (Schema::hasTable('mpesa_payments')) {
            Schema::table('mpesa_payments', function (Blueprint $table) {
                if (Schema::hasColumn('mpesa_payments', 'sale_id')) {
                    $table->unsignedBigInteger('sale_id')->nullable()->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (Schema::hasColumn('payments', 'sale_id')) {
                    $table->unsignedBigInteger('sale_id')->nullable(false)->change();
                }
            });
        }

        if (Schema::hasTable('mpesa_payments')) {
            Schema::table('mpesa_payments', function (Blueprint $table) {
                if (Schema::hasColumn('mpesa_payments', 'sale_id')) {
                    $table->unsignedBigInteger('sale_id')->nullable(false)->change();
                }
            });
        }
    }
};

