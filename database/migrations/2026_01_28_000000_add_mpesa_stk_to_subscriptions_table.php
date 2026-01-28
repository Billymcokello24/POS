<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'mpesa_stk')) {
                $table->string('mpesa_stk')->nullable()->after('transaction_id');
            }
            if (! Schema::hasColumn('subscriptions', 'mpesa_receipt')) {
                $table->string('mpesa_receipt')->nullable()->after('mpesa_stk');
            }
            if (! Schema::hasColumn('subscriptions', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('mpesa_receipt');
            }
            if (! Schema::hasColumn('subscriptions', 'activated_at')) {
                $table->timestamp('activated_at')->nullable()->after('verified_at');
            }
        });
    }

    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'activated_at')) $table->dropColumn('activated_at');
            if (Schema::hasColumn('subscriptions', 'verified_at')) $table->dropColumn('verified_at');
            if (Schema::hasColumn('subscriptions', 'mpesa_receipt')) $table->dropColumn('mpesa_receipt');
            if (Schema::hasColumn('subscriptions', 'mpesa_stk')) $table->dropColumn('mpesa_stk');
        });
    }
};

