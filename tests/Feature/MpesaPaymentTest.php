<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MpesaPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_initiate_mpesa_requires_phone_and_amount()
    {
        $response = $this->postJson('/api/payments/mpesa/stk-push', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone_number', 'amount']);
    }
}

