<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Product;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportsController extends Controller
{
    public function index()
    {
        $businessId = auth()->user()->current_business_id;
        $user = auth()->user();

        // Get quick stats for the dashboard - apply RBAC filtering
        $statsQuery = Sale::where('business_id', $businessId)
            ->where('status', 'completed');

        // RBAC: Cashiers can only see their own sales stats
        if ($user->isCashier()) {
            $statsQuery->where('cashier_id', $user->id);
        }

        $todaySales = (clone $statsQuery)->whereDate('created_at', today())->sum('total');
        $todayOrders = (clone $statsQuery)->whereDate('created_at', today())->count();

        // Product stats are not filtered by user role (all users can see all products)
        $totalProducts = Product::where('business_id', $businessId)
            ->where('is_active', true)
            ->count();

        $lowStockItems = Product::where('business_id', $businessId)
            ->where('track_inventory', true)
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->count();

        return Inertia::render('Reports/Index', [
            'stats' => [
                'today_sales' => (float) $todaySales,
                'today_orders' => (int) $todayOrders,
                'total_products' => (int) $totalProducts,
                'low_stock_items' => (int) $lowStockItems,
            ]
        ]);
    }

    public function sales(Request $request)
    {
        $businessId = auth()->user()->current_business_id;
        $user = auth()->user();

        $startDate = $request->input('start_date', now()->startOfMonth());
        $endDate = $request->input('end_date', now()->endOfMonth());

        // Sales summary with cost calculation - apply RBAC filtering
        $salesQuery = Sale::where('business_id', $businessId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed');

        // RBAC: Cashiers can only see their own sales
        if ($user->isCashier()) {
            $salesQuery->where('cashier_id', $user->id);
        }

        $salesData = $salesQuery->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(subtotal) as subtotal'),
                DB::raw('SUM(tax_amount) as tax'),
                DB::raw('SUM(discount_amount) as discount'),
                DB::raw('SUM(total) as total'),
            ])
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        // Calculate actual cost for each day by querying sale items - apply RBAC filtering
        $salesData = $salesData->map(function($day) use ($businessId, $user) {
            $costQuery = DB::table('sale_items')
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->join('products', 'sale_items.product_id', '=', 'products.id')
                ->where('sales.business_id', $businessId)
                ->whereDate('sales.created_at', $day->date)
                ->where('sales.status', 'completed');

            // RBAC: Cashiers can only see their own sales costs
            if ($user->isCashier()) {
                $costQuery->where('sales.cashier_id', $user->id);
            }

            $totalCost = $costQuery->select(DB::raw('SUM(sale_items.quantity * products.cost_price) as total_cost'))
                ->value('total_cost') ?? 0;

            $day->total_cost = $totalCost;
            $day->profit = $day->total - $totalCost;

            return $day;
        });

        // Top selling products - apply RBAC filtering
        $topProductsQuery = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.business_id', $businessId)
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->where('sales.status', 'completed');

        // RBAC: Cashiers can only see their own sales products
        if ($user->isCashier()) {
            $topProductsQuery->where('sales.cashier_id', $user->id);
        }

        $topProducts = $topProductsQuery->select([
                'sale_items.product_name',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.total) as total_revenue'),
            ])
            ->groupBy('sale_items.product_name')
            ->orderBy('total_revenue', 'desc')
            ->limit(10)
            ->get();

        // Payment methods breakdown - apply RBAC filtering
        $paymentMethodsQuery = DB::table('payments')
            ->join('sales', 'payments.sale_id', '=', 'sales.id')
            ->where('sales.business_id', $businessId)
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->where('payments.status', 'completed');

        // RBAC: Cashiers can only see their own sales payments
        if ($user->isCashier()) {
            $paymentMethodsQuery->where('sales.cashier_id', $user->id);
        }

        $paymentMethods = $paymentMethodsQuery->select([
                'payments.payment_method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(payments.amount) as total'),
            ])
            ->groupBy('payments.payment_method')
            ->get();

        // Get individual sales for scatter plot (last 7 days) - apply RBAC filtering
        $last7DaysStart = now()->subDays(6)->startOfDay();
        $individualSalesQuery = Sale::where('business_id', $businessId)
            ->whereBetween('created_at', [$last7DaysStart, now()->endOfDay()])
            ->where('status', 'completed');

        // RBAC: Cashiers can only see their own sales
        if ($user->isCashier()) {
            $individualSalesQuery->where('cashier_id', $user->id);
        }

        $individualSales = $individualSalesQuery->orderBy('created_at', 'asc')
            ->get()
            ->map(function($sale) {
                // Calculate cost for this sale
                $totalCost = DB::table('sale_items')
                    ->join('products', 'sale_items.product_id', '=', 'products.id')
                    ->where('sale_items.sale_id', $sale->id)
                    ->select(DB::raw('SUM(sale_items.quantity * products.cost_price) as total_cost'))
                    ->value('total_cost') ?? 0;

                return [
                    'id' => $sale->id,
                    'sale_number' => $sale->sale_number,
                    'total' => $sale->total,
                    'profit' => $sale->total - $totalCost,
                    'created_at' => $sale->created_at,
                    'day_of_week' => $sale->created_at->format('l'), // Monday, Tuesday, etc.
                    'day_index' => $sale->created_at->dayOfWeek, // 0 = Sunday, 1 = Monday, etc.
                ];
            });

        return Inertia::render('Reports/Sales', [
            'sales_data' => $salesData,
            'individual_sales' => $individualSales,
            'top_products' => $topProducts,
            'payment_methods' => $paymentMethods,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        ]);
    }

    public function inventory(Request $request)
    {
        $businessId = auth()->user()->current_business_id;

        // Current inventory status
        $inventoryStatus = Product::where('business_id', $businessId)
            ->with('category')
            ->select([
                'id',
                'name',
                'sku',
                'category_id',
                'quantity',
                'reorder_level',
                'cost_price',
                'selling_price',
                DB::raw('quantity * cost_price as inventory_value'),
            ])
            ->orderBy('quantity', 'asc')
            ->get();

        // Low stock items
        $lowStockItems = Product::where('business_id', $businessId)
            ->where('track_inventory', true)
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->with('category')
            ->get();

        // Inventory movements (last 30 days)
        $movements = InventoryTransaction::where('business_id', $businessId)
            ->where('created_at', '>=', now()->subDays(30))
            ->with(['product', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        // Inventory value by category
        $valueByCategory = Product::where('products.business_id', $businessId)
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select([
                'categories.name as category',
                DB::raw('SUM(products.quantity * products.cost_price) as value'),
                DB::raw('COUNT(products.id) as product_count'),
            ])
            ->groupBy('categories.id', 'categories.name')
            ->get();

        return Inertia::render('Reports/Inventory', [
            'inventory_status' => $inventoryStatus,
            'low_stock_items' => $lowStockItems,
            'movements' => $movements,
            'value_by_category' => $valueByCategory,
        ]);
    }

    public function exportInventory(Request $request)
    {
        $format = $request->input('format', 'csv'); // pdf, csv, excel
        $businessId = auth()->user()->current_business_id;

        // Get inventory data
        $inventoryStatus = Product::where('business_id', $businessId)
            ->with('category')
            ->select([
                'id',
                'name',
                'sku',
                'category_id',
                'quantity',
                'reorder_level',
                'cost_price',
                'selling_price',
                DB::raw('quantity * cost_price as inventory_value'),
            ])
            ->orderBy('quantity', 'asc')
            ->get();

        $lowStockItems = Product::where('business_id', $businessId)
            ->where('track_inventory', true)
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->with('category')
            ->get();

        $valueByCategory = Product::where('products.business_id', $businessId)
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select([
                'categories.name as category',
                DB::raw('SUM(products.quantity * products.cost_price) as value'),
                DB::raw('COUNT(products.id) as product_count'),
            ])
            ->groupBy('categories.id', 'categories.name')
            ->get();

        if ($format === 'csv') {
            return $this->exportInventoryToCSV($inventoryStatus, $lowStockItems, $valueByCategory);
        } elseif ($format === 'excel') {
            return $this->exportInventoryToExcel($inventoryStatus, $lowStockItems, $valueByCategory);
        } elseif ($format === 'pdf') {
            return $this->exportInventoryToPDF($inventoryStatus, $lowStockItems, $valueByCategory);
        }

        return response()->json(['message' => 'Invalid export format'], 400);
    }

    private function exportInventoryToCSV($inventoryStatus, $lowStockItems, $valueByCategory)
    {
        $filename = 'inventory-report-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($inventoryStatus, $lowStockItems, $valueByCategory) {
            $file = fopen('php://output', 'w');

            // Report header
            fputcsv($file, ['Inventory Report']);
            fputcsv($file, ['Generated', now()->format('Y-m-d H:i:s')]);
            fputcsv($file, []);

            // Summary
            $totalValue = $inventoryStatus->sum('inventory_value');
            fputcsv($file, ['Summary']);
            fputcsv($file, ['Total Inventory Value', number_format($totalValue, 2)]);
            fputcsv($file, ['Total Products', $inventoryStatus->count()]);
            fputcsv($file, ['Low Stock Items', $lowStockItems->count()]);
            fputcsv($file, []);

            // Inventory by category
            fputcsv($file, ['Inventory Value by Category']);
            fputcsv($file, ['Category', 'Product Count', 'Value']);

            foreach ($valueByCategory as $category) {
                fputcsv($file, [
                    $category->category,
                    $category->product_count,
                    number_format($category->value, 2),
                ]);
            }

            fputcsv($file, []);

            // All products
            fputcsv($file, ['Complete Inventory']);
            fputcsv($file, ['SKU', 'Product Name', 'Category', 'Quantity', 'Reorder Level', 'Cost Price', 'Selling Price', 'Inventory Value']);

            foreach ($inventoryStatus as $product) {
                fputcsv($file, [
                    $product->sku,
                    $product->name,
                    $product->category->name ?? 'Uncategorized',
                    $product->quantity,
                    $product->reorder_level,
                    number_format($product->cost_price, 2),
                    number_format($product->selling_price, 2),
                    number_format($product->inventory_value, 2),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportInventoryToExcel($inventoryStatus, $lowStockItems, $valueByCategory)
    {
        $filename = 'inventory-report-' . now()->format('Y-m-d-His') . '.xls';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($inventoryStatus, $lowStockItems, $valueByCategory) {
            $totalValue = $inventoryStatus->sum('inventory_value');

            echo '<table border="1">';
            echo '<tr><th colspan="8"><b>Inventory Report</b></th></tr>';
            echo '<tr><td colspan="8">Generated: ' . now()->format('Y-m-d H:i:s') . '</td></tr>';
            echo '<tr><td colspan="8"></td></tr>';

            echo '<tr><th colspan="3"><b>Summary</b></th></tr>';
            echo '<tr><td>Total Inventory Value</td><td colspan="2">' . number_format($totalValue, 2) . '</td></tr>';
            echo '<tr><td>Total Products</td><td colspan="2">' . $inventoryStatus->count() . '</td></tr>';
            echo '<tr><td>Low Stock Items</td><td colspan="2">' . $lowStockItems->count() . '</td></tr>';
            echo '<tr><td colspan="8"></td></tr>';

            echo '<tr><th colspan="3"><b>Inventory Value by Category</b></th></tr>';
            echo '<tr><th>Category</th><th>Product Count</th><th>Value</th></tr>';

            foreach ($valueByCategory as $category) {
                echo '<tr>';
                echo '<td>' . $category->category . '</td>';
                echo '<td>' . $category->product_count . '</td>';
                echo '<td>' . number_format($category->value, 2) . '</td>';
                echo '</tr>';
            }

            echo '<tr><td colspan="8"></td></tr>';
            echo '<tr><th colspan="8"><b>Complete Inventory</b></th></tr>';
            echo '<tr><th>SKU</th><th>Product Name</th><th>Category</th><th>Quantity</th><th>Reorder Level</th><th>Cost Price</th><th>Selling Price</th><th>Inventory Value</th></tr>';

            foreach ($inventoryStatus as $product) {
                echo '<tr>';
                echo '<td>' . $product->sku . '</td>';
                echo '<td>' . $product->name . '</td>';
                echo '<td>' . ($product->category->name ?? 'Uncategorized') . '</td>';
                echo '<td>' . $product->quantity . '</td>';
                echo '<td>' . $product->reorder_level . '</td>';
                echo '<td>' . number_format($product->cost_price, 2) . '</td>';
                echo '<td>' . number_format($product->selling_price, 2) . '</td>';
                echo '<td>' . number_format($product->inventory_value, 2) . '</td>';
                echo '</tr>';
            }

            echo '</table>';
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportInventoryToPDF($inventoryStatus, $lowStockItems, $valueByCategory)
    {
        $filename = 'inventory-report-' . now()->format('Y-m-d-His') . '.pdf';

        $totalValue = $inventoryStatus->sum('inventory_value');
        $totalProducts = $inventoryStatus->count();
        $lowStockCount = $lowStockItems->count();

        // Get business information
        $business = \App\Models\Business::find(auth()->user()->current_business_id);

        $html = view('reports.inventory-pdf', [
            'inventoryStatus' => $inventoryStatus,
            'lowStockItems' => $lowStockItems,
            'valueByCategory' => $valueByCategory,
            'totalValue' => $totalValue,
            'totalProducts' => $totalProducts,
            'lowStockCount' => $lowStockCount,
            'business' => $business,
        ])->render();

        $pdf = Pdf::loadHTML($html);
        return $pdf->stream($filename);
    }

    public function export(Request $request)
    {
        $type = $request->input('type', 'sales'); // sales, inventory, financial
        $format = $request->input('format', 'csv'); // pdf, csv, excel

        $businessId = auth()->user()->current_business_id;
        $startDate = $request->input('start_date', now()->startOfMonth());
        $endDate = $request->input('end_date', now()->endOfMonth());

        if ($type === 'sales') {
            // Get sales data
            $salesData = Sale::where('business_id', $businessId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'completed')
                ->select([
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as total_orders'),
                    DB::raw('SUM(subtotal) as subtotal'),
                    DB::raw('SUM(tax_amount) as tax'),
                    DB::raw('SUM(discount_amount) as discount'),
                    DB::raw('SUM(total) as total'),
                ])
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get();

            $topProducts = DB::table('sale_items')
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->where('sales.business_id', $businessId)
                ->whereBetween('sales.created_at', [$startDate, $endDate])
                ->where('sales.status', 'completed')
                ->select([
                    'sale_items.product_name',
                    DB::raw('SUM(sale_items.quantity) as total_quantity'),
                    DB::raw('SUM(sale_items.total) as total_revenue'),
                ])
                ->groupBy('sale_items.product_name')
                ->orderBy('total_revenue', 'desc')
                ->limit(10)
                ->get();

            if ($format === 'csv') {
                return $this->exportSalesToCSV($salesData, $topProducts, $startDate, $endDate);
            } elseif ($format === 'excel') {
                return $this->exportSalesToExcel($salesData, $topProducts, $startDate, $endDate);
            } elseif ($format === 'pdf') {
                return $this->exportSalesToPDF($salesData, $topProducts, $startDate, $endDate);
            }
        }

        return response()->json(['message' => 'Invalid export type or format'], 400);
    }

    private function exportSalesToCSV($salesData, $topProducts, $startDate, $endDate)
    {
        $filename = 'sales-report-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($salesData, $topProducts, $startDate, $endDate) {
            $file = fopen('php://output', 'w');

            // Report header
            fputcsv($file, ['Sales Report']);
            fputcsv($file, ['Period', $startDate . ' to ' . $endDate]);
            fputcsv($file, []);

            // Daily sales
            fputcsv($file, ['Daily Sales Breakdown']);
            fputcsv($file, ['Date', 'Total Orders', 'Subtotal', 'Tax', 'Discount', 'Total']);

            foreach ($salesData as $day) {
                fputcsv($file, [
                    $day->date,
                    $day->total_orders,
                    number_format($day->subtotal, 2),
                    number_format($day->tax, 2),
                    number_format($day->discount, 2),
                    number_format($day->total, 2),
                ]);
            }

            fputcsv($file, []);

            // Top products
            fputcsv($file, ['Top Selling Products']);
            fputcsv($file, ['Product Name', 'Total Quantity', 'Total Revenue']);

            foreach ($topProducts as $product) {
                fputcsv($file, [
                    $product->product_name,
                    $product->total_quantity,
                    number_format($product->total_revenue, 2),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportSalesToExcel($salesData, $topProducts, $startDate, $endDate)
    {
        // For now, return same as CSV with different extension
        // In production, you'd use Laravel Excel package
        $filename = 'sales-report-' . now()->format('Y-m-d-His') . '.xls';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($salesData, $topProducts, $startDate, $endDate) {
            echo '<table border="1">';
            echo '<tr><th colspan="6"><b>Sales Report</b></th></tr>';
            echo '<tr><td colspan="6">Period: ' . $startDate . ' to ' . $endDate . '</td></tr>';
            echo '<tr><td colspan="6"></td></tr>';

            echo '<tr><th colspan="6"><b>Daily Sales Breakdown</b></th></tr>';
            echo '<tr><th>Date</th><th>Orders</th><th>Subtotal</th><th>Tax</th><th>Discount</th><th>Total</th></tr>';

            foreach ($salesData as $day) {
                echo '<tr>';
                echo '<td>' . $day->date . '</td>';
                echo '<td>' . $day->total_orders . '</td>';
                echo '<td>' . number_format($day->subtotal, 2) . '</td>';
                echo '<td>' . number_format($day->tax, 2) . '</td>';
                echo '<td>' . number_format($day->discount, 2) . '</td>';
                echo '<td>' . number_format($day->total, 2) . '</td>';
                echo '</tr>';
            }

            echo '<tr><td colspan="6"></td></tr>';
            echo '<tr><th colspan="3"><b>Top Selling Products</b></th></tr>';
            echo '<tr><th>Product</th><th>Quantity</th><th>Revenue</th></tr>';

            foreach ($topProducts as $product) {
                echo '<tr>';
                echo '<td>' . $product->product_name . '</td>';
                echo '<td>' . $product->total_quantity . '</td>';
                echo '<td>' . number_format($product->total_revenue, 2) . '</td>';
                echo '</tr>';
            }

            echo '</table>';
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportSalesToPDF($salesData, $topProducts, $startDate, $endDate)
    {
        $filename = 'sales-report-' . now()->format('Y-m-d-His') . '.pdf';

        $totalRevenue = $salesData->sum('total');
        $totalOrders = $salesData->sum('total_orders');
        $totalTax = $salesData->sum('tax');

        // Get business information
        $business = \App\Models\Business::find(auth()->user()->current_business_id);

        $html = view('reports.sales-pdf', [
            'salesData' => $salesData,
            'topProducts' => $topProducts,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'totalRevenue' => $totalRevenue,
            'totalOrders' => $totalOrders,
            'totalTax' => $totalTax,
            'business' => $business,
        ])->render();

        $pdf = Pdf::loadHTML($html);
        return $pdf->stream($filename);
    }
}
