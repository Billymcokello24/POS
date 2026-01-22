<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\TaxConfiguration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Milon\Barcode\Facades\DNS1DFacade;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $businessId = auth()->user()->current_business_id;

        // If no business_id, return empty data to trigger Vue fallback
        if (!$businessId) {
            return Inertia::render('Products/Index', [
                'products' => [
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                ],
                'categories' => [],
                'filters' => [],
            ]);
        }

        $products = Product::with(['category', 'taxConfiguration'])
            ->where('business_id', $businessId)
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%")
                      ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->when($request->category_id, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($request->low_stock, function ($query) {
                $query->whereColumn('quantity', '<=', 'reorder_level');
            })
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        $categories = Category::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category_id', 'low_stock']),
        ]);
    }

    public function create()
    {
        $businessId = auth()->user()->current_business_id;

        $categories = Category::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $taxConfigurations = TaxConfiguration::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        return Inertia::render('Products/Create', [
            'categories' => $categories,
            'taxConfigurations' => $taxConfigurations,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'sku' => 'nullable|string|unique:products,sku',
            'barcode' => 'nullable|string|unique:products,barcode',
            'barcode_type' => 'nullable|in:EAN13,UPCA,CODE128',
            'description' => 'nullable|string',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'unit' => 'nullable|string|max:50',
            'track_inventory' => 'boolean',
            'is_active' => 'boolean',
            'tax_configuration_id' => 'nullable|exists:tax_configurations,id',
        ]);

        $businessId = auth()->user()->current_business_id;

        // If user doesn't have a business, assign them to the first business or create one
        if (!$businessId) {
            $business = \App\Models\Business::first();
            if (!$business) {
                $business = \App\Models\Business::create([
                    'name' => 'Default Business',
                    'business_type' => 'retail',
                    'address' => 'Default Address',
                    'phone' => '0000000000',
                    'email' => 'business@example.com',
                    'receipt_prefix' => 'REC',
                    'currency' => 'USD',
                ]);
            }
            $businessId = $business->id;
            auth()->user()->update(['current_business_id' => $businessId]);
        }

        $validated['business_id'] = $businessId;

        // Generate SKU if not provided
        if (empty($validated['sku'])) {
            $validated['sku'] = 'SKU-' . Str::upper(Str::random(8));
        }

        // Generate barcode if not provided
        if (empty($validated['barcode'])) {
            $validated['barcode'] = $this->generateUniqueBarcode();
            $validated['barcode_type'] = 'CODE128';
        }

        $product = Product::create($validated);

        // Create initial inventory transaction
        if ($validated['quantity'] > 0) {
            $product->increaseStock(
                $validated['quantity'],
                'IN',
                null,
                auth()->id()
            );
        }

        return redirect()->route('products.index')
            ->with('success', 'Product created successfully.');
    }

    public function show(Product $product)
    {
        // Check business ownership
        if ($product->business_id !== auth()->user()->current_business_id) {
            abort(403, 'Unauthorized');
        }

        $product->load(['category', 'taxConfiguration', 'inventoryTransactions.createdBy']);

        return Inertia::render('Products/Show', [
            'product' => $product,
        ]);
    }

    public function edit(Product $product)
    {
        // Check business ownership
        if ($product->business_id !== auth()->user()->current_business_id) {
            abort(403, 'Unauthorized');
        }

        $businessId = auth()->user()->current_business_id;

        $categories = Category::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $taxConfigurations = TaxConfiguration::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        return Inertia::render('Products/Edit', [
            'product' => $product,
            'categories' => $categories,
            'taxConfigurations' => $taxConfigurations,
        ]);
    }

    public function update(Request $request, Product $product)
    {
        // Check business ownership
        if ($product->business_id !== auth()->user()->current_business_id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'sku' => 'nullable|string|unique:products,sku,' . $product->id,
            'barcode' => 'nullable|string|unique:products,barcode,' . $product->id,
            'description' => 'nullable|string',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'quantity' => 'nullable|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'unit' => 'nullable|string|max:50',
            'track_inventory' => 'boolean',
            'is_active' => 'boolean',
            'tax_configuration_id' => 'nullable|exists:tax_configurations,id',
        ]);

        $product->update($validated);

        return back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        // Check business ownership
        if ($product->business_id !== auth()->user()->current_business_id) {
            abort(403, 'Unauthorized');
        }

        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Product deleted successfully.');
    }

    public function search(Request $request)
    {
        $businessId = auth()->user()->current_business_id;
        $query = $request->input('q');

        $products = Product::where('business_id', $businessId)
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%")
                  ->orWhere('barcode', $query);
            })
            ->with('category', 'taxConfiguration')
            ->limit(10)
            ->get();

        return response()->json($products);
    }

    public function scanBarcode(Request $request)
    {
        $businessId = auth()->user()->current_business_id;
        $barcode = $request->input('barcode');

        $product = Product::where('business_id', $businessId)
            ->where('barcode', $barcode)
            ->where('is_active', true)
            ->with('category', 'taxConfiguration')
            ->first();

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        return response()->json($product);
    }

    private function generateUniqueBarcode(): string
    {
        do {
            $barcode = str_pad(rand(0, 999999999999), 12, '0', STR_PAD_LEFT);
        } while (Product::where('barcode', $barcode)->exists());

        return $barcode;
    }
}
