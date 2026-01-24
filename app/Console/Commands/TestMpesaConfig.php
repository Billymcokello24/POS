<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PaymentService;

class TestMpesaConfig extends Command
{
    protected $signature = 'mpesa:test-stk {phone?} {amount?}';
    protected $description = 'Simulate an STK push using PaymentService (honors mpesa.simulate)';

    public function handle(PaymentService $paymentService)
    {
        $phone = $this->argument('phone') ?? '0712345678';
        $amount = $this->argument('amount') ?? 10;

        $this->info("Testing STK push to {$phone} for amount {$amount}");

        $result = $paymentService->initiateMpesaStkPush([
            'phone_number' => $phone,
            'amount' => $amount,
            'account_reference' => 'CLI-TEST',
        ]);

        $this->line('Result: ' . json_encode($result));

        return 0;
    }
}

