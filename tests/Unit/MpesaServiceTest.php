<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\MpesaService;

class MpesaServiceTest extends TestCase
{
    public function test_validate_phone_formats_correctly()
    {
        $svc = new MpesaService();

        $this->assertEquals('254712345678', $svc->validatePhoneNumber('0712345678'));
        $this->assertEquals('254712345678', $svc->validatePhoneNumber('+254712345678'));
        $this->assertEquals('254712345678', $svc->validatePhoneNumber('712345678'));
        $this->assertFalse($svc->validatePhoneNumber('12345'));
    }

    public function test_generate_password_returns_password_and_timestamp()
    {
        $svc = new MpesaService();
        $result = $this->invokeGeneratePassword($svc);

        $this->assertArrayHasKey('password', $result);
        $this->assertArrayHasKey('timestamp', $result);
        // timestamp should be 14 chars YmdHis
        $this->assertMatchesRegularExpression('/^\d{14}$/', $result['timestamp']);
    }

    protected function invokeGeneratePassword(MpesaService $svc)
    {
        // use reflection to call protected method
        $ref = new \ReflectionClass($svc);
        $method = $ref->getMethod('generatePassword');
        $method->setAccessible(true);
        return $method->invoke($svc);
    }
}

