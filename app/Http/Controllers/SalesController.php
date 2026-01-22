<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $businessId = auth()->user()->current_business_id;

        $sales = Sale::with(['cashier', 'customer', 'payments'])
            ->where('business_id', $businessId)
            ->when($request->search, function ($query, $search) {
                $query->where('sale_number', 'like', "%{$search}%");
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->date_from, function ($query, $date) {
                $query->whereDate('created_at', '>=', $date);
            })
            ->when($request->date_to, function ($query, $date) {
                $query->whereDate('created_at', '<=', $date);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        // Calculate stats
        $todayRevenue = Sale::where('business_id', $businessId)
            ->whereDate('created_at', today())
            ->where('status', 'completed')
            ->sum('total');

        $totalSalesCount = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->count();

        $avgSaleValue = $totalSalesCount > 0
            ? Sale::where('business_id', $businessId)
                ->where('status', 'completed')
                ->avg('total')
            : 0;

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'filters' => $request->only(['search', 'status', 'date_from', 'date_to']),
            'stats' => [
                'today_revenue' => (float) $todayRevenue,
                'total_sales' => (int) $totalSalesCount,
                'avg_sale_value' => (float) $avgSaleValue,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $businessId = auth()->user()->current_business_id;

        $sales = Sale::with(['cashier', 'customer', 'payments'])
            ->where('business_id', $businessId)
            ->when($request->search, function ($query, $search) {
                $query->where('sale_number', 'like', "%{$search}%");
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->date_from, function ($query, $date) {
                $query->whereDate('created_at', '>=', $date);
            })
            ->when($request->date_to, function ($query, $date) {
                $query->whereDate('created_at', '<=', $date);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'sales-export-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($sales) {
            $file = fopen('php://output', 'w');

            // Add CSV headers
            fputcsv($file, [
                'Sale Number',
                'Date',
                'Time',
                'Cashier',
                'Customer',
                'Subtotal',
                'Tax',
                'Discount',
                'Total',
                'Status',
                'Payment Methods',
            ]);

            // Add data rows
            foreach ($sales as $sale) {
                $paymentMethods = $sale->payments->pluck('payment_method')->join(', ');

                fputcsv($file, [
                    $sale->sale_number,
                    $sale->created_at->format('Y-m-d'),
                    $sale->created_at->format('H:i:s'),
                    $sale->cashier->name ?? 'N/A',
                    $sale->customer->name ?? 'Walk-in',
                    number_format($sale->subtotal, 2),
                    number_format($sale->tax_amount, 2),
                    number_format($sale->discount_amount, 2),
                    number_format($sale->total, 2),
                    $sale->status,
                    $paymentMethods,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function create()
    {
        $businessId = auth()->user()->current_business_id;

        $customers = Customer::where('business_id', $businessId)
            ->orderBy('name')
            ->get();

        // Get business currency setting
        $business = \App\Models\Business::find($businessId);
        $currency = $business->currency ?? 'KES'; // Default to KES (Kenyan Shilling)

        return Inertia::render('Sales/Create', [
            'customers' => $customers,
            'currency' => $currency,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'payments' => 'required|array|min:1',
            'payments.*.payment_method' => 'required|in:CASH,CARD,MPESA,BANK_TRANSFER',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference_number' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $businessId = auth()->user()->current_business_id;

        try {
            DB::beginTransaction();

            // Calculate totals
            $subtotal = 0;
            $taxAmount = 0;

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $itemSubtotal = $item['quantity'] * $item['unit_price'];
                $itemDiscount = $item['discount_amount'] ?? 0;
                $subtotal += ($itemSubtotal - $itemDiscount);

                // Calculate tax if product has tax configuration
                if ($product->taxConfiguration) {
                    $taxAmount += $product->taxConfiguration->calculateTax($itemSubtotal - $itemDiscount);
                }
            }

            $discountAmount = $validated['discount_amount'] ?? 0;
            $total = $subtotal + $taxAmount - $discountAmount;

            // Create sale
            $sale = Sale::create([
                'business_id' => $businessId,
                'cashier_id' => auth()->id(),
                'customer_id' => $validated['customer_id'] ?? null,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total' => $total,
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
                'completed_at' => now(),
            ]);

            // Create sale items and update inventory
            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                $itemSubtotal = $item['quantity'] * $item['unit_price'];
                $itemDiscount = $item['discount_amount'] ?? 0;
                $itemTaxAmount = 0;

                if ($product->taxConfiguration) {
                    $itemTaxAmount = $product->taxConfiguration->calculateTax($itemSubtotal - $itemDiscount);
                }

                $itemTotal = $itemSubtotal - $itemDiscount + $itemTaxAmount;

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $product->taxConfiguration?->rate ?? 0,
                    'tax_amount' => $itemTaxAmount,
                    'discount_amount' => $itemDiscount,
                    'subtotal' => $itemSubtotal,
                    'total' => $itemTotal,
                ]);

                // Decrease inventory
                $product->decreaseStock($item['quantity'], 'SALE', $sale, auth()->id());
            }

            // Create payments
            foreach ($validated['payments'] as $payment) {
                Payment::create([
                    'sale_id' => $sale->id,
                    'payment_method' => $payment['payment_method'],
                    'amount' => $payment['amount'],
                    'reference_number' => $payment['reference_number'] ?? null,
                    'status' => 'completed',
                ]);
            }

            // Update customer statistics
            if ($validated['customer_id']) {
                $customer = Customer::find($validated['customer_id']);
                $customer->increment('total_spent', $total);
                $customer->increment('total_visits');
                $customer->update(['last_visit_at' => now()]);
            }

            DB::commit();

            // Redirect to sales create with flash data for Inertia
            return redirect()->route('sales.create')->with([
                'success' => 'Sale completed successfully',
                'saleId' => $sale->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return back()
                ->withErrors(['error' => 'Failed to process sale: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function show(Sale $sale)
    {
        // Check business ownership
        if ($sale->business_id !== auth()->user()->current_business_id) {
            abort(403, 'Unauthorized');
        }

        $sale->load(['items.product', 'payments', 'cashier', 'customer']);

        return Inertia::render('Sales/Show', [
            'sale' => $sale,
        ]);
    }

    public function receipt(Sale $sale)
    {
        // Check business ownership
        if ($sale->business_id !== auth()->user()->current_business_id) {
            abort(403, 'Unauthorized');
        }

        $sale->load(['items.product', 'payments', 'cashier', 'customer', 'business']);

        $pdf = Pdf::loadView('receipts.sale', ['sale' => $sale]);

        // Stream instead of download so it opens in browser
        return $pdf->stream('receipt-' . $sale->sale_number . '.pdf');
    }

    public function refund(Request $request, Sale $sale)
    {
        // Check business ownership
        if ($sale->business_id !== auth()->user()->current_business_id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'reason' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|exists:sale_items,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            foreach ($validated['items'] as $item) {
                $saleItem = SaleItem::findOrFail($item['sale_item_id']);

                if ($item['quantity'] > $saleItem->quantity) {
                    throw new \Exception('Refund quantity cannot exceed sale quantity');
                }

                // Return stock to inventory
                $product = $saleItem->product;
                $product->increaseStock($item['quantity'], 'RETURN', $sale, auth()->id());
            }

            $sale->update([
                'status' => 'refunded',
                'notes' => ($sale->notes ?? '') . "\nRefund reason: " . $validated['reason'],
            ]);

            DB::commit();

            return redirect()->route('sales.show', $sale)
                ->with('success', 'Sale refunded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
