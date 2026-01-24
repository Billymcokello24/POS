<?php

namespace Tests\Unit;

use App\Services\AIAgentService;
use PHPUnit\Framework\TestCase;

class AIAgentServiceTest extends TestCase
{
    public function test_search_inventory_returns_array()
    {
        $service = new AIAgentService();
        $res = $service->searchInventory(['query' => 'nonexisting']);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('count', $res);
        $this->assertArrayHasKey('products', $res);
        $this->assertArrayHasKey('summary', $res);
    }

    public function test_generate_report_returns_report_structure()
    {
        $service = new AIAgentService();
        $res = $service->generateReport(['range' => 'last_30_days']);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('report', $res);
        $this->assertArrayHasKey('narrative', $res);
    }

    public function test_slow_moving_returns_array()
    {
        $service = new AIAgentService();
        $res = $service->slowMovingProducts(30, 5);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('products', $res);
    }
}

