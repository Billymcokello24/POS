<?php

namespace App\Services;

use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIAgentService
{
    // Search inventory by query criteria (sku, name, category, etc.)
    public function searchInventory(array $criteria = []): array
    {
        $query = Product::query();

        if (!empty($criteria['query'])) {
            $q = $criteria['query'];
            $query->where(function ($qry) use ($q) {
                $qry->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%");
            });
        }

        if (!empty($criteria['category_id'])) {
            $query->where('category_id', $criteria['category_id']);
        }

        if (!empty($criteria['limit'])) {
            $limit = (int) $criteria['limit'];
        } else {
            $limit = 50;
        }

        // Determine which price column exists and select it defensively
        $selectFields = ['id', 'sku', 'name', 'quantity'];
        $priceColumn = null;
        if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'selling_price')) {
            $priceColumn = 'selling_price';
            $selectFields[] = 'selling_price';
        } elseif (\Illuminate\Support\Facades\Schema::hasColumn('products', 'price')) {
            $priceColumn = 'price';
            $selectFields[] = 'price';
        }

        $products = $query->limit($limit)->get($selectFields)->toArray();

        // Normalize returned product shape: include a 'price' key for compatibility
        foreach ($products as $idx => $prod) {
            $products[$idx]['price'] = $priceColumn ? ($prod[$priceColumn] ?? null) : null;
        }

        $summary = $this->aiResponse('summarize_search', ['products' => $products, 'criteria' => $criteria]);

        return [
            'count' => count($products),
            'products' => $products,
            'summary' => $summary,
        ];
    }

    // Generate a simple report (mocked) based on params like date range
    public function generateReport(array $params = []): array
    {
        $range = $params['range'] ?? 'last_30_days';

        // For safety, return simple aggregates from DB where possible
        $since = Carbon::now()->subDays(30);
        if ($range === 'last_7_days') {
            $since = Carbon::now()->subDays(7);
        } elseif ($range === 'last_90_days') {
            $since = Carbon::now()->subDays(90);
        }

        $totalProducts = Product::count();
        $lowStockCount = Product::where('quantity', '<', 5)->count();

        $report = [
            'range' => $range,
            'total_products' => $totalProducts,
            'low_stock_count' => $lowStockCount,
        ];

        $narrative = $this->aiResponse('report_narrative', ['report' => $report]);

        return [
            'report' => $report,
            'narrative' => $narrative,
        ];
    }

    // Find slow-moving products based on lack of sales activity. This implementation uses a simple heuristic: products with low 'sold_count' column or old 'updated_at' and low stock movement. If sales data is unavailable, fallback to oldest updated products.
    public function slowMovingProducts(int $days = 60, int $limit = 20): array
    {
        // If the products table contains a `sold_count` column use it, otherwise fallback
        $query = Product::query();

        if (Schema::hasColumn('products', 'sold_count')) {
            $query->orderBy('sold_count', 'asc');
        } elseif (Schema::hasColumn('products', 'last_sold_at')) {
            $threshold = Carbon::now()->subDays($days);
            $query->where(function ($q) use ($threshold) {
                $q->whereNull('last_sold_at')->orWhere('last_sold_at', '<', $threshold);
            })->orderBy('last_sold_at', 'asc');
        } else {
            // Fallback to least recently updated
            $query->orderBy('updated_at', 'asc');
        }

        $products = $query->limit($limit)->get(['id', 'sku', 'name', 'quantity', 'updated_at'])->toArray();

        $explanation = $this->aiResponse('slow_moving_explanation', ['products' => $products, 'days' => $days]);

        return [
            'count' => count($products),
            'products' => $products,
            'explanation' => $explanation,
        ];
    }

    // Check product availability by SKU or ID across locations/stores - simplified to product quantity for now
    public function productAvailability(string $identifier): array
    {
        $product = Product::where('sku', $identifier)->orWhere('id', $identifier)->first();

        if (!$product) {
            return ['found' => false, 'message' => 'Product not found'];
        }

        // If location-level inventory exists, it should be aggregated here. For now return the product's quantity and basic meta.
        $availability = [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'quantity' => $product->quantity ?? 0,
            'locations' => [],
        ];

        $note = $this->aiResponse('availability_note', ['product' => $availability]);

        return [
            'found' => true,
            'availability' => $availability,
            'note' => $note,
        ];
    }

    /**
     * aiResponse chooses a driver based on config and returns a string output.
     * Supported drivers: 'mock' (default) and 'openai' (OpenAI-compatible HTTP API).
     */
    public function aiResponse(string $type, array $context = []): string
    {
        // If an API key is configured, prefer the OpenAI driver so responses are live and not static.
        $apiKey = config('ai.api_key');
        $driver = config('ai.driver', 'mock');
        if (!empty($apiKey)) {
            $driver = 'openai';
        }

        if ($driver === 'openai') {
            return $this->callOpenAi($type, $context);
        }

        return $this->callAiMock($type, $context);
    }

    private function callOpenAi(string $type, array $context = []): string
    {
        $apiKey = config('ai.api_key');
        $model = config('ai.model', 'gpt-5');
        $base = rtrim(config('ai.base_uri', 'https://api.openai.com'), '/');
        $timeout = (int) config('ai.timeout_seconds', 10);

        if (!$apiKey) {
            return '[AI disabled: missing API key]';
        }

        try {
            // Build a more conversational system prompt so non-chat responses are not rigid
            $prompt = $this->buildPrompt($type, $context);

            $messages = [];
            $baseSystem = 'You are an expert, conversational inventory and sales assistant for a retail business. Use any provided JSON context as helpful background information, but answer naturally and creatively: you are free to explain, hypothesize, provide suggestions, and ask clarifying questions. Do not be restricted to rigid templates. Only return machine-readable JSON if the user explicitly requests it.';
            $messages[] = ['role' => 'system', 'content' => $baseSystem];

            if (!empty($context['hint'])) {
                $messages[] = ['role' => 'system', 'content' => (string) $context['hint']];
            }

            $messages[] = ['role' => 'user', 'content' => $prompt];

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout($timeout)->post("{$base}/v1/chat/completions", [
                'model' => $model,
                'messages' => $messages,
                // allow longer outputs for reports and open replies
                'max_tokens' => 1200,
            ]);

            if ($response->successful()) {
                $body = $response->json();
                // Log for debugging (local only) — trimmed to avoid huge logs
                try {
                    Log::debug('OpenAI response', ['type' => $type, 'choices' => array_slice($body['choices'] ?? [], 0, 1)]);
                } catch (\Exception $e) {
                    // ignore logging errors
                }

                // Safely extract text
                if (isset($body['choices'][0]['message']['content'])) {
                    return trim($body['choices'][0]['message']['content']);
                }
                if (isset($body['choices'][0]['text'])) {
                    return trim($body['choices'][0]['text']);
                }

                return '[AI returned an unexpected response]';
            }

            // Log non-successful responses
            try {
                Log::warning('OpenAI request failed', ['status' => $response->status(), 'body' => $response->body()]);
            } catch (\Exception $e) {
                // ignore
            }

            return '[AI request failed] ' . $response->status();
        } catch (\Exception $e) {
            Log::error('OpenAI error', ['exception' => $e->getMessage()]);
            return '[AI error] ' . $e->getMessage();
        }
    }


    private function buildPrompt(string $type, array $context = []): string
    {
        switch ($type) {
            case 'summarize_search':
                $count = isset($context['products']) ? count($context['products']) : 0;
                return "Summarize the search results (count: {$count}) and provide quick tips for refining the search.";
            case 'report_narrative':
                $r = $context['report'] ?? [];
                return "Write a short narrative summary for this report: " . json_encode($r);
            case 'slow_moving_explanation':
                return "Explain why these products might be slow-moving and suggest actions to improve turnover.";
            case 'availability_note':
                return "Provide a short note about product availability given this availability object: " . json_encode($context['product'] ?? []);
            default:
                return "Provide a short explanation.";
        }
    }

    /**
     * Chat endpoint - accepts a user message and optional context and returns a reply.
     * Payload: ['message' => string, 'context' => array]
     */
    public function chat(array $payload = []): array
    {
        $message = isset($payload['message']) ? trim((string) $payload['message']) : '';
        $context = $payload['context'] ?? [];

        if ($message === '') {
            return ['reply' => '', 'error' => 'Empty message'];
        }

        // Build a factual hint/context to ground the AI broadly
        $hints = [];

        // Basic business snapshot (fast aggregates)
        try {
            $hints['totals'] = [
                'total_products' => \App\Models\Product::count(),
                'total_sales_overall' => (float) \App\Models\Sale::sum('total'),
                'total_orders_overall' => (int) \App\Models\Sale::count(),
            ];
        } catch (\Exception $e) {
            // ignore
        }

        // Top products by quantity sold (uses sale_items if available)
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('sale_items')) {
                $top = \App\Models\SaleItem::selectRaw('product_id, SUM(quantity) as qty_sold')
                    ->groupBy('product_id')
                    ->orderByDesc('qty_sold')
                    ->limit(5)
                    ->get()
                    ->map(function ($row) {
                        $p = \App\Models\Product::find($row->product_id);
                        return [
                            'product_id' => $row->product_id,
                            'name' => $p?->name ?? null,
                            'sku' => $p?->sku ?? null,
                            'qty_sold' => (int) $row->qty_sold,
                        ];
                    })->toArray();

                $hints['top_products'] = $top;
            }
        } catch (\Exception $e) {
            // ignore
        }

        // Low stock list
        try {
            $low = \App\Models\Product::where('quantity', '<', 5)->limit(10)->get(['id','sku','name','quantity'])->toArray();
            $hints['low_stock'] = $low;
        } catch (\Exception $e) {
            // ignore
        }

        // If message references a timeframe like 'last 7 days' or 'last 30 days', compute those aggregates and include
        if (preg_match('/last\s*(7|30|90)\s*days/i', $message, $m)) {
            $days = (int) $m[1];
            try {
                $since = Carbon::now()->subDays($days);
                $salesSince = \App\Models\Sale::where('created_at', '>=', $since);
                if (Schema::hasColumn('sales', 'status')) {
                    $salesSince->where('status','completed');
                }
                $hints['range_aggregates'] = [
                    'range' => "last_{$days}_days",
                    'total_sales' => (float) $salesSince->sum('total'),
                    'orders_count' => (int) $salesSince->count(),
                ];
            } catch (\Exception $e) {
                // ignore
            }
        }

        // If message references cashier or user, include sales by cashier (last 30 days)
        if (preg_match('/cashier|who (?:sold|made) the sale|by (?:cashier|user)/i', $message)) {
            try {
                $since = Carbon::now()->subDays(30);
                $byCashier = \App\Models\Sale::selectRaw('cashier_id, COUNT(*) as orders, SUM(total) as total')
                    ->where('created_at', '>=', $since)
                    ->groupBy('cashier_id')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->get()
                    ->map(function ($r) {
                        $u = \App\Models\User::find($r->cashier_id);
                        return [
                            'cashier_id' => $r->cashier_id,
                            'cashier' => $u?->name ?? null,
                            'orders' => (int) $r->orders,
                            'sales_total' => (float) $r->total,
                        ];
                    })->toArray();
                $hints['sales_by_cashier_30d'] = $byCashier;
            } catch (\Exception $e) {
                // ignore
            }
        }

        // Existing SKU detection and sale lookup
        if (preg_match('/\bSKU[-_]?[A-Z0-9]+\b/i', $message, $m)) {
            $sku = $m[0];
            try {
                $product = \App\Models\Product::where('sku', $sku)->first();
                if ($product) {
                    $hints['product'] = [
                        'id' => $product->id,
                        'sku' => $product->sku,
                        'name' => $product->name,
                        'quantity' => $product->quantity,
                        'price' => $product->selling_price ?? $product->price ?? null,
                    ];
                }
            } catch (\Exception $e) {}
        }

        // Sale number lookup
        if (preg_match('/sale(?: number| #|:)?\s*([A-Z0-9-_]+)/i', $message, $m)) {
            $sn = $m[1];
            try {
                $sale = \App\Models\Sale::where('sale_number', $sn)->with('items','cashier','customer')->first();
                if ($sale) {
                    $saleItems = [];
                    foreach ($sale->items as $it) {
                        $saleItems[] = [
                            'product_id' => $it->product_id,
                            'name' => $it->name ?? null,
                            'sku' => $it->sku ?? null,
                            'quantity' => $it->quantity,
                            'price' => $it->price ?? null,
                        ];
                    }
                    $hints['sale_lookup'] = [
                        'sale_number' => $sale->sale_number,
                        'cashier' => $sale->cashier?->name ?? null,
                        'customer' => $sale->customer?->name ?? null,
                        'total' => $sale->total,
                        'completed_at' => $sale->completed_at?->toDateTimeString() ?? null,
                        'items' => $saleItems,
                    ];
                }
            } catch (\Exception $e) {}
        }

        // Merge any existing context.hint passed by caller
        if (!empty($context['hint'])) {
            $hints['caller_hint'] = $context['hint'];
        }

        if (!empty($hints)) {
            $context['hint'] = json_encode($hints);
        }

        $driver = config('ai.driver', 'mock');

        if ($driver === 'openai') {
            $reply = $this->callOpenAiChat($message, $context);
        } else {
            if (!empty($hints)) {
                $summaryParts = [];
                if (!empty($hints['report'])) {
                    $r = $hints['report'];
                    $summaryParts[] = "Report ({$r['period_start']} to {$r['period_end']}) total_sales={$r['total_sales']} orders={$r['orders_count']}";
                }
                if (!empty($hints['recent_sales_count'])) {
                    $summaryParts[] = "Recent sales (last 7 days): {$hints['recent_sales_count']} records.";
                }
                if (!empty($hints['top_products'])) {
                    $summaryParts[] = 'Top products: ' . implode(', ', array_map(function($p){ return ($p['sku'] ?? $p['name'] ?? 'n/a'); }, $hints['top_products']));
                }
                $reply = implode(' ', $summaryParts) . ' — (mock) ' . $this->callAiMock('chat', ['message' => $message]);
            } else {
                $reply = $this->callAiMock('chat', ['message' => $message, 'context' => $context]);
            }
        }

        return ['reply' => $reply];
    }

    private function callOpenAiChat(string $message, array $context = []): string
    {
        $apiKey = config('ai.api_key');
        $model = config('ai.model', 'gpt-5');
        $base = rtrim(config('ai.base_uri', 'https://api.openai.com'), '/');
        $timeout = (int) config('ai.timeout_seconds', 10);

        if (!$apiKey) {
            return '[AI disabled: missing API key]';
        }

        try {
            // Build messages array. Include a system prompt with limited context about the business to guide the assistant.
            $messages = [];

            $baseSystem = 'You are a friendly, conversational assistant for a retail business. Use any provided JSON context as background information if it helps, but feel free to respond in a natural, open-ended way: offer insights, suggestions, and ask clarifying questions. You may extrapolate or hypothesize when appropriate; if the user needs exact numbers, indicate what came from the data and what is an estimate. Only return machine-readable JSON if explicitly requested.';
            $messages[] = ['role' => 'system', 'content' => $baseSystem];

            // User message
            $messages[] = ['role' => 'user', 'content' => $message];

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout($timeout)->post("{$base}/v1/chat/completions", [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => 1200,
            ]);

            if ($response->successful()) {
                $body = $response->json();
                try {
                    Log::debug('OpenAI chat response', ['choices' => array_slice($body['choices'] ?? [], 0, 1)]);
                } catch (\Exception $e) {}

                if (isset($body['choices'][0]['message']['content'])) {
                    return trim($body['choices'][0]['message']['content']);
                }
                if (isset($body['choices'][0]['text'])) {
                    return trim($body['choices'][0]['text']);
                }

                return '[AI returned an unexpected response]';
            }

            try {
                Log::warning('OpenAI chat request failed', ['status' => $response->status(), 'body' => $response->body()]);
            } catch (\Exception $e) {}

            return '[AI request failed] ' . $response->status();
        } catch (\Exception $e) {
            Log::error('OpenAI chat error', ['exception' => $e->getMessage()]);
            return '[AI error] ' . $e->getMessage();
        }
    }

    // A simple deterministic mock for AI outputs so the system can run without external APIs
    private function callAiMock(string $type, array $context = [])
    {
        switch ($type) {
            case 'summarize_search':
                $count = isset($context['products']) ? count($context['products']) : 0;
                return "Found {$count} matching products. Use filters to refine results.";
            case 'report_narrative':
                $r = $context['report'] ?? [];
                return "Report for {$r['range']} includes {$r['total_products']} products; {$r['low_stock_count']} are low on stock.";
            case 'slow_moving_explanation':
                return "These products have low recent activity based on sales data or last updated timestamps.";
            case 'availability_note':
                return "Availability shown is aggregated from main inventory. Check location-level inventory for details.";
            case 'chat':
                // Produce a dynamic, non-static reply based on DB and context
                $msg = $context['message'] ?? '';
                $hints = $context['hints'] ?? ($context['hint'] ?? null);
                return $this->generateLocalReply($msg, $hints);
            default:
                return '';
        }
    }

    // Generate a local, open-ended reply from DB when no external AI is available.
    private function generateLocalReply(string $message, $hints = null): string
    {
        $msg = trim(strtolower($message));

        // Prioritize explicit requests
        try {
            // Report for this month
            if (preg_match('/\b(report|summary)\b.*\b(this month|this month|current month)\b/i', $message) || preg_match('/\bthis month\b/i', $message)) {
                $start = Carbon::now()->startOfMonth();
                $end = Carbon::now()->endOfMonth();
                $salesQuery = \App\Models\Sale::whereBetween('created_at', [$start, $end]);
                if (Schema::hasColumn('sales','status')) {
                    $salesQuery->where('status','completed');
                }
                $totalSales = (float) $salesQuery->sum('total');
                $orders = (int) $salesQuery->count();
                $totalProducts = \App\Models\Product::count();
                $lowStock = (int) \App\Models\Product::where('quantity','<',5)->count();

                $suggestions = [];
                if ($lowStock > 0) $suggestions[] = "restock low-stock items ({$lowStock})";
                if ($orders < 10) $suggestions[] = 'run a promotional campaign';

                $sugg = $suggestions ? ('Suggestions: ' . implode('; ', $suggestions) . '.') : '';

                return "Report for {$start->toDateString()} to {$end->toDateString()}: total sales $" . number_format($totalSales,2) . " across {$orders} orders. We have {$totalProducts} products in catalog; {$lowStock} items are low on stock. {$sugg}";
            }

            // Last N days
            if (preg_match('/last\s*(7|30|90)\s*days/i', $message, $m)) {
                $days = (int)$m[1];
                $since = Carbon::now()->subDays($days);
                $salesQ = \App\Models\Sale::where('created_at','>=',$since);
                if (Schema::hasColumn('sales','status')) $salesQ->where('status','completed');
                $totalSales = (float) $salesQ->sum('total');
                $orders = (int) $salesQ->count();
                return "In the last {$days} days we made $" . number_format($totalSales,2) . " across {$orders} orders.";
            }

            // Low stock
            if (preg_match('/low stock|need to restock|running out/i', $message)) {
                $items = \App\Models\Product::where('quantity','<',5)->limit(10)->get(['sku','name','quantity'])->toArray();
                if (empty($items)) return 'No low-stock items found.';
                $lines = array_map(fn($p) => "{$p['sku']} - {$p['name']}: {$p['quantity']}", $items);
                return "Low stock items:\n" . implode("\n", $lines);
            }

            // SKU or product lookup
            if (preg_match('/\bSKU[-_]?[A-Z0-9]+\b/i', $message, $m)) {
                $sku = $m[0];
                $product = \App\Models\Product::where('sku',$sku)->first();
                if ($product) {
                    $recentSales = \App\Models\SaleItem::where('product_id',$product->id)->limit(5)->get()->toArray() ?? [];
                    $recentCount = count($recentSales);
                    $price = $product->selling_price ?? $product->price ?? null;
                    return "Product {$product->sku} - {$product->name}: quantity={$product->quantity}, price={$price}. Recent sales records: {$recentCount}.";
                }
                return "No product found with SKU {$sku}.";
            }

            // Sales by cashier
            if (preg_match('/who (?:sold|made) the sale|by cashier|sales by cashier/i', $message)) {
                $since = Carbon::now()->subDays(30);
                $by = \App\Models\Sale::selectRaw('cashier_id, COUNT(*) as cnt, SUM(total) as total')
                    ->where('created_at','>=',$since)
                    ->groupBy('cashier_id')
                    ->orderByDesc('total')
                    ->limit(5)
                    ->get();
                if ($by->isEmpty()) return 'No cashier sales data available for the period.';
                $parts = [];
                foreach ($by as $r) {
                    $u = \App\Models\User::find($r->cashier_id);
                    $parts[] = ($u?->name ?? 'Unknown') . " ({$r->cnt} orders, $" . number_format($r->total,2) . ")";
                }
                return 'Top cashiers (30d): ' . implode('; ', $parts);
            }

            // General fallback: search products by keywords
            $terms = preg_split('/\s+/', trim(preg_replace('/[^a-z0-9 ]/i',' ', $message)));
            if (count($terms) > 0) {
                $q = \App\Models\Product::query();
                foreach ($terms as $t) {
                    if (strlen($t) < 2) continue;
                    $q->orWhere('name','like',"%{$t}%")->orWhere('sku','like',"%{$t}%");
                }
                $found = $q->limit(10)->get(['sku','name','quantity'])->toArray();
                if (!empty($found)) {
                    $lines = array_map(fn($p) => "{$p['sku']} - {$p['name']} (qty: {$p['quantity']})", $found);
                    return "Search results:\n" . implode("\n", $lines);
                }
            }

            return "Sorry, I couldn't find direct data for that query; try asking for 'low stock', 'sales for last 7 days', 'find SKU-...' or a product name.";
        } catch (\Exception $e) {
            return '[Error generating reply] ' . $e->getMessage();
        }
    }
}
