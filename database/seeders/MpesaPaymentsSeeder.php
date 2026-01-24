<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MpesaPaymentsSeeder extends Seeder
{
    public function run()
    {
        DB::table('mpesa_payments')->insert([
            [
                'checkout_request_id' => 'SIM-CHECKOUT-TEST-1',
                'merchant_request_id' => 'SIM-MERCHANT-TEST-1',
                'phone' => '254700000001',
                'amount' => 100.00,
                'reference' => 'SEED-1',
                'status' => 'completed',
                'metadata' => json_encode(['note' => 'seeded test payment']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

