<?php

namespace App\Services\Http\Controllers\Api;

use App\Services\Http\Controllers\Controller;
use App\Services\AIAgentService;
use Illuminate\Http\Request;

class AIAgentController extends Controller
{
    protected AIAgentService $service;

    public function __construct(AIAgentService $service)
    {
        $this->service = $service;
    }

    public function searchInventory(Request $request)
    {
        $data = $request->validate([
            'query' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer'],
        ]);

        $result = $this->service->searchInventory($data);

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function generateReport(Request $request)
    {
        $data = $request->validate([
            'range' => ['nullable', 'string'],
        ]);

        $result = $this->service->generateReport($data);

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function slowMovingProducts(Request $request)
    {
        $days = (int) $request->query('days', 60);
        $limit = (int) $request->query('limit', 20);

        $result = $this->service->slowMovingProducts($days, $limit);

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function productAvailability(Request $request)
    {
        $sku = $request->query('sku') ?? $request->query('id') ?? $request->input('identifier');

        if (!$sku) {
            return response()->json(['success' => false, 'message' => 'sku or id required'], 422);
        }

        $result = $this->service->productAvailability($sku);

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function chat(Request $request)
    {
        $data = $request->validate([
            'message' => ['required', 'string'],
            'context' => ['nullable', 'array'],
        ]);

        $result = $this->service->chat($data);

        return response()->json(['success' => true, 'data' => $result]);
    }
}

