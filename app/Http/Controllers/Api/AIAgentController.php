<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AIAgentService;
use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Business;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

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

    public function generateBusinessPDF(Request $request)
    {
        $data = $request->validate([
            'period' => ['required', 'string', 'in:today,week,month,year'],
        ]);

        $user = auth()->user();
        $businessId = $user->current_business_id;
        $business = Business::findOrFail($businessId);

        // Determine date range
        $startDate = match($data['period']) {
            'today' => Carbon::today(),
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'year' => Carbon::now()->startOfYear(),
        };
        $endDate = Carbon::now();

        // Fetch Products with prices
        $products = Product::where('business_id', $businessId)
            ->select('id', 'name', 'sku', 'selling_price', 'cost_price', 'quantity', 'category_id')
            ->with('category:id,name')
            ->orderBy('name')
            ->get();

        // Fetch Sales data
        $salesQuery = Sale::where('business_id', $businessId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed');

        if ($user->isCashier()) {
            $salesQuery->where('cashier_id', $user->id);
        }

        $sales = $salesQuery->with('items.product')->get();

        $totalRevenue = $sales->sum('total');
        $totalOrders = $sales->count();

        // Calculate total cost and profit
        $totalCost = 0;
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $totalCost += ($item->product->cost_price ?? 0) * $item->quantity;
            }
        }

        $grossProfit = $totalRevenue - $totalCost;
        $profitMargin = $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0;

        // Top selling products
        $topProducts = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.business_id', $businessId)
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->where('sales.status', 'completed')
            ->when($user->isCashier(), function($q) use ($user) {
                return $q->where('sales.cashier_id', $user->id);
            })
            ->select([
                'products.name',
                'products.sku',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.total) as total_revenue'),
            ])
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderBy('total_revenue', 'desc')
            ->limit(10)
            ->get();

        // Payment methods
        $paymentMethods = DB::table('payments')
            ->join('sales', 'payments.sale_id', '=', 'sales.id')
            ->where('sales.business_id', $businessId)
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->where('payments.status', 'completed')
            ->when($user->isCashier(), function($q) use ($user) {
                return $q->where('sales.cashier_id', $user->id);
            })
            ->select([
                'payments.payment_method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(payments.amount) as total'),
            ])
            ->groupBy('payments.payment_method')
            ->get();

        // Prepare data for PDF
        $pdfData = [
            'business' => $business,
            'period' => ucfirst($data['period']),
            'startDate' => $startDate->format('M d, Y'),
            'endDate' => $endDate->format('M d, Y'),
            'generatedAt' => Carbon::now()->format('M d, Y h:i A'),
            'products' => $products,
            'topProducts' => $topProducts,
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'gross_profit' => $grossProfit,
                'profit_margin' => $profitMargin,
                'total_orders' => $totalOrders,
                'average_order_value' => $totalOrders > 0 ? $totalRevenue / $totalOrders : 0,
            ],
            'paymentMethods' => $paymentMethods,
            'currency' => $business->currency ?? 'USD',
        ];

        $pdf = Pdf::loadView('reports.ai-business-report', $pdfData);
        $filename = "{$business->name}-Report-{$data['period']}-" . Carbon::now()->format('Y-m-d') . ".pdf";

        return $pdf->download($filename);
    }
}
