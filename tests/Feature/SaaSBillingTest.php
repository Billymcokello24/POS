<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\MpesaPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SaaSBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed basic plans with required slugs
        Plan::create(['id' => 1, 'name' => 'Basic', 'slug' => 'basic', 'price_monthly' => 1000, 'is_active' => true]);
        Plan::create(['id' => 2, 'name' => 'Premium', 'slug' => 'premium', 'price_monthly' => 3000, 'is_active' => true]);
    }

    public function test_stk_callback_authoritative_activation()
    {
        $business = Business::create(['name' => 'Test Biz', 'plan_id' => 1]);
        $user = User::factory()->create(['current_business_id' => $business->id]);
        
        // 1. Initiate a subscription
        $subscription = Subscription::create([
            'business_id' => $business->id,
            'plan_id' => 2,
            'plan_name' => 'Premium',
            'amount' => 3000,
            'status' => Subscription::STATUS_PENDING,
            'checkout_request_id' => 'ws_CO_30012026123456789'
        ]);

        // Create MpesaPayment initiation record
        MpesaPayment::create([
            'business_id' => $business->id,
            'checkout_request_id' => 'ws_CO_30012026123456789',
            'amount' => 3000,
            'status' => MpesaPayment::STATUS_PENDING
        ]);

        // 2. Simulate M-Pesa Callback
        $callbackData = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '12345-67890-1',
                    'CheckoutRequestID' => 'ws_CO_30012026123456789',
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => 3000],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'TXL1234567'],
                            ['Name' => 'TransactionDate', 'Value' => 20260130123456],
                            ['Name' => 'PhoneNumber', 'Value' => 254712345678]
                        ]
                    ]
                ]
            ]
        ];

        $job = new \App\Jobs\ProcessMpesaCallback($callbackData);
        $job->handle();

        // 3. Verify Ledger Truth
        $payment = MpesaPayment::where('checkout_request_id', 'ws_CO_30012026123456789')->first();
        $this->assertEquals(0, $payment->result_code);
        $this->assertEquals(MpesaPayment::STATUS_SUCCESS, $payment->status);
        $this->assertEquals('TXL1234567', $payment->receipt);

        // 4. Verify Subscription State (Deterministic Truth)
        $subscription->refresh();
        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertTrue($subscription->is_active);
        $this->assertTrue($subscription->is_verified);
        $this->assertEquals('TXL1234567', $subscription->mpesa_receipt);
        $this->assertNotNull($subscription->activated_at);

        // 5. Verify Business State
        $business->refresh();
        $this->assertEquals(2, $business->plan_id);
        $this->assertNotNull($business->plan_ends_at);
    }

    public function test_failed_stk_callback_updates_ledger_but_not_activation()
    {
         $business = Business::create(['name' => 'Test Biz', 'plan_id' => 1]);
         
         $subscription = Subscription::create([
             'business_id' => $business->id,
             'plan_id' => 2,
             'plan_name' => 'Premium',
             'amount' => 3000,
             'status' => Subscription::STATUS_PENDING,
             'checkout_request_id' => 'ws_FAILED_123'
         ]);

         MpesaPayment::create([
             'business_id' => $business->id,
             'checkout_request_id' => 'ws_FAILED_123',
             'amount' => 3000,
             'status' => MpesaPayment::STATUS_PENDING
         ]);

         $callbackData = [
             'Body' => [
                 'stkCallback' => [
                     'CheckoutRequestID' => 'ws_FAILED_123',
                     'ResultCode' => 1032, // Cancelled by user
                     'ResultDesc' => 'Request cancelled by user',
                 ]
             ]
         ];

         $job = new \App\Jobs\ProcessMpesaCallback($callbackData);
         $job->handle();

         $payment = MpesaPayment::where('checkout_request_id', 'ws_FAILED_123')->first();
         $this->assertEquals(1032, $payment->result_code);
         $this->assertEquals(MpesaPayment::STATUS_FAILED, $payment->status);

         $subscription->refresh();
         $this->assertEquals(Subscription::STATUS_CANCELLED, $subscription->status); // MpesaPayment status FAILED maps to CANCELLED in refactor
         $this->assertFalse($subscription->is_active);

         $business->refresh();
         $this->assertEquals(1, $business->plan_id); // Stayed on Basic
    }
}
