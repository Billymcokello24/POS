<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\InventoryTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $businessId = auth()->user()->current_business_id;

        $products = Product::where('business_id', $businessId)
            ->with(['category'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($request->low_stock, function ($query) {
                $query->whereColumn('quantity', '<=', 'reorder_level');
            })
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Inventory/Index', [
            'products' => $products,
            'filters' => $request->only(['search', 'low_stock']),
        ]);
    }

    public function adjust(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'adjustment_type' => 'required|in:IN,OUT,ADJUSTMENT',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        // Check if product belongs to current business
        if ($product->business_id !== auth()->user()->current_business_id) {
            abort(403, 'Unauthorized');
        }

        try {
            DB::beginTransaction();

            if ($validated['adjustment_type'] === 'IN') {
                $product->increaseStock(
                    $validated['quantity'],
                    'ADJUSTMENT',
                    null,
                    auth()->id()
                );
            } else {
                $product->decreaseStock(
                    $validated['quantity'],
                    'ADJUSTMENT',
                    null,
                    auth()->id()
                );
            }

            // Update the last transaction with notes
            $lastTransaction = $product->inventoryTransactions()->latest()->first();
            if ($lastTransaction) {
                $lastTransaction->update([
                    'notes' => $validated['reason'] . (isset($validated['notes']) ? ': ' . $validated['notes'] : ''),
                ]);
            }

            DB::commit();

            return back()->with('success', 'Inventory adjusted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to adjust inventory: ' . $e->getMessage()]);
        }
    }

    public function transactions(Request $request)
    {
        $businessId = auth()->user()->current_business_id;

        $transactions = InventoryTransaction::where('business_id', $businessId)
            ->with(['product', 'createdBy'])
            ->when($request->product_id, function ($query, $productId) {
                $query->where('product_id', $productId);
            })
            ->when($request->type, function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($request->date_from, function ($query, $date) {
                $query->whereDate('created_at', '>=', $date);
            })
            ->when($request->date_to, function ($query, $date) {
                $query->whereDate('created_at', '<=', $date);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return Inertia::render('Inventory/Transactions', [
            'transactions' => $transactions,
            'filters' => $request->only(['product_id', 'type', 'date_from', 'date_to']),
        ]);
    }

    public function export(Request $request)
    {
        $businessId = auth()->user()->current_business_id;

        $products = Product::with(['category'])
            ->where('business_id', $businessId)
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($request->low_stock, function ($query) {
                $query->whereColumn('quantity', '<=', 'reorder_level');
            })
            ->orderBy('name')
            ->get();

        $filename = 'inventory-export-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($products) {
            $file = fopen('php://output', 'w');

            // Add CSV headers
            fputcsv($file, [
                'Product Name',
                'SKU',
                'Category',
                'Current Stock',
                'Reorder Level',
                'Cost Price',
                'Selling Price',
                'Stock Value',
                'Status',
            ]);

            // Add data rows
            foreach ($products as $product) {
                fputcsv($file, [
                    $product->name,
                    $product->sku,
                    $product->category->name ?? 'Uncategorized',
                    $product->quantity,
                    $product->reorder_level,
                    number_format($product->cost_price, 2),
                    number_format($product->selling_price, 2),
                    number_format($product->quantity * $product->cost_price, 2),
                    $product->is_low_stock ? 'Low Stock' : 'In Stock',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
