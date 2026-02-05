<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\TaxConfiguration;
use App\Http\Controllers\Controller;
use App\Jobs\ImportProductsJob;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        if (! auth()->user()->hasPermission('view_products')) {
            abort(403, 'Unauthorized');
        }
        $businessId = auth()->user()->current_business_id;

        // If no business_id, return empty data to trigger Vue fallback
        if (! $businessId) {
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
                'businesses' => auth()->user()->businesses()->select('businesses.id as id', 'businesses.name as name')->get(),
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

        // provide the list of businesses the user belongs to so the frontend can import into multiple
        $businesses = auth()->user()->businesses()->select('businesses.id as id', 'businesses.name as name')->get();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category_id', 'low_stock']),
            'businesses' => $businesses,
        ]);
    }

    public function create()
    {
        if (! auth()->user()->hasPermission('create_products')) {
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

        return Inertia::render('Products/Create', [
            'categories' => $categories,
            'taxConfigurations' => $taxConfigurations,
        ]);
    }

    public function store(Request $request)
    {
        if (! auth()->user()->hasPermission('create_products')) {
            abort(403, 'Unauthorized');
        }

        try {
            // Debug log: record attempt to create a product with basic context
            try {
                \Illuminate\Support\Facades\Log::info('Product store attempt', [
                    'user_id' => auth()->id(),
                    'business_id' => auth()->user()?->current_business_id ?? null,
                    'input' => array_slice($request->all(), 0, 20),
                ]);
            } catch (\Throwable $__e) {
                // ignore logging errors
            }
            // determine business first so validation uniqueness can be scoped to business
            $businessId = auth()->user()->current_business_id;
            if (! $businessId) {
                $business = \App\Models\Business::first();
                if (! $business) {
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

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'category_id' => 'nullable|exists:categories,id',
                'sku' => [
                    'nullable','string',
                    Rule::unique('products')->where(function ($query) use ($businessId) {
                        return $query->where('business_id', $businessId);
                    }),
                ],
                'barcode' => [
                    'nullable','string',
                    Rule::unique('products')->where(function ($query) use ($businessId) {
                        return $query->where('business_id', $businessId);
                    }),
                ],
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

            $validated['business_id'] = $businessId;

            // Generate SKU if not provided
            if (empty($validated['sku'])) {
                $validated['sku'] = 'SKU-'.Str::upper(Str::random(8));
            }

            // Barcode must be entered manually - no auto-generation
            // Set default barcode_type if barcode is provided without type
            if (! empty($validated['barcode']) && empty($validated['barcode_type'])) {
                $validated['barcode_type'] = 'CODE128';
            }

            // Plan Limit Check
            $business = \App\Models\Business::find($businessId);
            if ($business && ! $business->withinPlanLimits('products')) {
                return back()->with('error', 'Your current plan limit for products ('.$business->plan->max_products.') has been reached. Please upgrade to add more.');
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

            // Push SSE event for product created so dashboard clients can refresh
            try {
                \App\Services\SseService::pushBusinessEvent($businessId, 'product.created', ['id' => $product->id, 'name' => $product->name]);
            } catch (\Throwable $e) {
                // don't block normal flow on SSE errors
            }

            // Notify all users in the business
            $business = \App\Models\Business::find($businessId);
            if ($business) {
                foreach ($business->users as $user) {
                    $user->notify(new \App\Notifications\ProductCreatedNotification($product));
                }
            }

            return redirect()->route('products.index')
                ->with('success', 'Product created successfully.');
        } catch (\Illuminate\Validation\ValidationException $ve) {
            // Let Laravel handle validation exceptions normally
            throw $ve;
        } catch (\Throwable $e) {
            // Log full exception to help diagnosis and return friendly error to user
            \Illuminate\Support\Facades\Log::error('Product store failed: '.$e->getMessage(), ['exception' => (string) $e]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Failed to create product', 'error' => $e->getMessage()], 500);
            }

            return back()->withInput()->with('error', 'Failed to create product: '.$e->getMessage());
        }
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
        if (! auth()->user()->hasPermission('edit_products')) {
            abort(403, 'Unauthorized');
        }
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
        if (! auth()->user()->hasPermission('edit_products')) {
            abort(403, 'Unauthorized');
        }
        // Check business ownership
        if ($product->business_id !== auth()->user()->current_business_id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'sku' => [
                'nullable','string',
                Rule::unique('products')->ignore($product->id)->where(function ($query) use ($product) {
                    return $query->where('business_id', $product->business_id);
                }),
            ],
            'barcode' => [
                'nullable','string',
                Rule::unique('products')->ignore($product->id)->where(function ($query) use ($product) {
                    return $query->where('business_id', $product->business_id);
                }),
            ],
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

        // Push SSE event for product updated
        try {
            \App\Services\SseService::pushBusinessEvent($product->business_id, 'product.updated', ['id' => $product->id, 'name' => $product->name]);
        } catch (\Throwable $e) {
            // ignore
        }

        return back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        if (! auth()->user()->hasPermission('delete_products')) {
            abort(403, 'Unauthorized');
        }
        // Check business ownership
        if ($product->business_id !== auth()->user()->current_business_id) {
            abort(403, 'Unauthorized');
        }

        $product->delete();

        // Push SSE event for product deleted
        try {
            \App\Services\SseService::pushBusinessEvent($product->business_id, 'product.deleted', ['id' => $product->id]);
        } catch (\Throwable $e) {
            // ignore
        }

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

        if (! $product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        return response()->json($product);
    }

    public function import(Request $request)
    {
        if (! auth()->user()->hasPermission('create_products')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xls,xlsx',
            'business_ids' => 'nullable|array',
            'business_ids.*' => 'integer|exists:businesses,id',
        ]);

        $businessId = auth()->user()->current_business_id;
        $targetBusinessIds = $request->input('business_ids', null);
        if (empty($targetBusinessIds)) {
            $targetBusinessIds = [$businessId];
        }

        $path = $request->file('file')->getRealPath();
        $ext = $request->file('file')->getClientOriginalExtension();

        $rows = [];
        $headers = [];
        $warnings = [];

        try {
            if (in_array(strtolower($ext), ['xlsx', 'xls'])) {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
                $spreadsheet = $reader->load($path);
                $sheet = $spreadsheet->getActiveSheet();
                $data = $sheet->toArray();

                // Validate header exists
                if (empty($data) || ! is_array($data[0])) {
                    return back()->with('error', 'Uploaded file is empty or has invalid format.');
                }

                $headers = array_map('strtolower', array_map('trim', $data[0]));

                // Validate headers against allowed list and collect warnings for unknown columns
                $allowed = [
                    'name', 'product_name', 'sku', 'barcode', 'category', 'description',
                    'cost_price', 'cost', 'selling_price', 'price', 'sale_price',
                    'quantity', 'qty', 'reorder_level', 'reorder', 'min_stock', 'unit',
                ];
                $unknown = array_values(array_diff($headers, $allowed));
                if (! empty($unknown)) {
                    $warnings[] = 'Unknown columns ignored: '.implode(', ', $unknown).'. Use the template if unsure.';
                }

                // Validate headers contain at least 'name'
                if (! in_array('name', $headers) && ! in_array('product_name', $headers)) {
                    return back()->with('error', 'Missing required header: name (or product_name). Please use the provided template.');
                }

                for ($i = 1; $i < count($data); $i++) {
                    $row = [];
                    foreach ($headers as $idx => $header) {
                        $row[$header] = isset($data[$i][$idx]) ? $data[$i][$idx] : null;
                    }
                    $rows[] = $row;
                }
            } else {
                $file = fopen($path, 'r');
                $header = null;
                $rowNumber = 0;
                while (($line = fgetcsv($file, 0, ',')) !== false) {
                    $rowNumber++;
                    if ($rowNumber === 1) {
                        $header = array_map('strtolower', array_map('trim', $line));
                        $allowed = [
                            'name', 'product_name', 'sku', 'barcode', 'category', 'description',
                            'cost_price', 'cost', 'selling_price', 'price', 'sale_price',
                            'quantity', 'qty', 'reorder_level', 'reorder', 'min_stock', 'unit',
                        ];
                        $unknown = array_values(array_diff($header, $allowed));
                        if (! empty($unknown)) {
                            // collect warning but continue parsing rows; unknown columns will be ignored
                            $warnings[] = 'Unknown columns ignored: '.implode(', ', $unknown).'. Use the template if unsure.';
                        }
                        // Validate headers
                        if (! in_array('name', $header) && ! in_array('product_name', $header)) {
                            fclose($file);

                            return back()->with('error', 'Missing required header: name (or product_name). Please use the provided template.');
                        }
                        $headers = $header;

                        continue;
                    }
                    $row = [];
                    foreach ($header as $idx => $h) {
                        $row[$h] = isset($line[$idx]) ? $line[$idx] : null;
                    }
                    $rows[] = $row;
                }
                fclose($file);
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to parse uploaded file: '.$e->getMessage());
        }

        // If large import (>50 rows), dispatch to queue for async processing
        if (count($rows) > 50) {
            ImportProductsJob::dispatch($rows, $targetBusinessIds, auth()->id());

            return back()->with('success', 'Large import queued! You will be notified when the import is complete. Importing ' . count($rows) . ' products in the background...');
        }

        $created = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // account for header row

            // Basic mapping and validation
            $name = $row['name'] ?? $row['product_name'] ?? null;
            if (! $name || trim($name) === '') {
                $errors[] = "Row {$rowNum}: missing required field 'name'.";

                continue;
            }

            $sku = isset($row['sku']) ? trim((string) $row['sku']) : null;
            $barcode = isset($row['barcode']) ? trim((string) $row['barcode']) : null;
            $categoryName = isset($row['category']) ? trim((string) $row['category']) : null;

            // Prices
            $costPriceRaw = $row['cost_price'] ?? $row['cost'] ?? null;
            $sellingPriceRaw = $row['selling_price'] ?? $row['price'] ?? $row['sale_price'] ?? null;
            $quantityRaw = $row['quantity'] ?? $row['qty'] ?? null;
            $reorderRaw = $row['reorder_level'] ?? $row['reorder'] ?? $row['min_stock'] ?? null;
            $unit = isset($row['unit']) ? trim((string) $row['unit']) : 'pcs';

            // Numeric parsing with validation
            $costPrice = 0.0;
            if ($costPriceRaw !== null && $costPriceRaw !== '') {
                if (! is_numeric($costPriceRaw)) {
                    $errors[] = "Row {$rowNum}: invalid cost_price value '{$costPriceRaw}'. Must be numeric.";

                    continue;
                }
                $costPrice = floatval($costPriceRaw);
                if ($costPrice < 0) {
                    $errors[] = "Row {$rowNum}: cost_price cannot be negative.";

                    continue;
                }
            }

            $sellingPrice = 0.0;
            if ($sellingPriceRaw !== null && $sellingPriceRaw !== '') {
                if (! is_numeric($sellingPriceRaw)) {
                    $errors[] = "Row {$rowNum}: invalid selling_price value '{$sellingPriceRaw}'. Must be numeric.";

                    continue;
                }
                $sellingPrice = floatval($sellingPriceRaw);
                if ($sellingPrice < 0) {
                    $errors[] = "Row {$rowNum}: selling_price cannot be negative.";

                    continue;
                }
            }

            $quantity = 0;
            if ($quantityRaw !== null && $quantityRaw !== '') {
                if (! is_numeric($quantityRaw)) {
                    $errors[] = "Row {$rowNum}: invalid quantity value '{$quantityRaw}'. Must be an integer.";

                    continue;
                }
                $quantity = intval($quantityRaw);
                if ($quantity < 0) {
                    $errors[] = "Row {$rowNum}: quantity cannot be negative.";

                    continue;
                }
            }

            $reorder = 0;
            if ($reorderRaw !== null && $reorderRaw !== '') {
                if (! is_numeric($reorderRaw)) {
                    $errors[] = "Row {$rowNum}: invalid reorder_level value '{$reorderRaw}'. Must be an integer.";

                    continue;
                }
                $reorder = intval($reorderRaw);
                if ($reorder < 0) {
                    $errors[] = "Row {$rowNum}: reorder_level cannot be negative.";

                    continue;
                }
            }

            // Create product for each target business (allow same product across businesses)
            foreach ($targetBusinessIds as $targetBusinessId) {
                // Category per business
                $categoryIdPerBusiness = null;
                if ($categoryName) {
                    $cat = Category::firstOrCreate([
                        'business_id' => $targetBusinessId,
                        'name' => $categoryName,
                    ], [
                        'slug' => Str::slug($categoryName),
                        'is_active' => true,
                    ]);
                    $categoryIdPerBusiness = $cat->id;
                }

                // SKU: if provided, avoid conflicts per business; otherwise generate per business
                $skuToUse = $sku;
                if (empty($skuToUse)) {
                    $skuToUse = $this->generateUniqueSku($targetBusinessId);
                } else {
                    if (Product::where('business_id', $targetBusinessId)->where('sku', $skuToUse)->exists()) {
                        $skuToUse = $skuToUse.'-'.Str::upper(Str::random(4));
                    }
                }

                // Barcode handling: ensure per-business uniqueness or generate
                $barcodeToUse = $barcode;
                if (empty($barcodeToUse)) {
                    $barcodeToUse = $this->generateUniqueBarcode($targetBusinessId);
                } else {
                    if (Product::where('business_id', $targetBusinessId)->where('barcode', $barcodeToUse)->exists()) {
                        $barcodeToUse = $this->generateUniqueBarcode($targetBusinessId);
                    }
                }

                try {
                    $product = Product::create([
                        'business_id' => $targetBusinessId,
                        'name' => $name,
                        'sku' => $skuToUse,
                        'barcode' => $barcodeToUse,
                        'description' => $row['description'] ?? null,
                        'category_id' => $categoryIdPerBusiness,
                        'cost_price' => $costPrice,
                        'selling_price' => $sellingPrice,
                        'quantity' => $quantity,
                        'reorder_level' => $reorder,
                        'unit' => $unit,
                        'track_inventory' => true,
                        'is_active' => true,
                    ]);

                    if ($quantity > 0) {
                        $product->increaseStock($quantity, 'IN', null, auth()->id());
                    }

                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = "Row {$rowNum} (business {$targetBusinessId}): failed to create product - ".$e->getMessage();

                    // continue to next business
                    continue;
                }
            }
        }

        $message = "Imported {$created} products.";
        if (! empty($errors)) {
            $message .= ' '.count($errors).' rows had errors.';
        }

        $flash = ['success' => $message, 'import_errors' => $errors];
        if (! empty($warnings)) {
            $flash['import_warnings'] = $warnings;
        }

        return back()->with($flash);
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="products-import-template.csv"',
        ];

        $columns = [
            'name', 'sku', 'barcode', 'category', 'description', 'cost_price', 'selling_price', 'quantity', 'reorder_level', 'unit',
        ];

        $callback = function () use ($columns) {
            $out = fopen('php://output', 'w');
            // Header
            fputcsv($out, $columns);
            // Example row
            fputcsv($out, [
                'Tea Green Organic', '', '', 'Beverages', 'Organic green tea 100g', '8.00', '12.00', '20', '5', 'pcs',
            ]);
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function generateUniqueBarcode($businessId = null): string
    {
        do {
            $barcode = str_pad(rand(0, 999999999999), 12, '0', STR_PAD_LEFT);
            if ($businessId) {
                $exists = Product::where('business_id', $businessId)->where('barcode', $barcode)->exists();
            } else {
                $exists = Product::where('barcode', $barcode)->exists();
            }
        } while ($exists);

        return $barcode;
    }

    private function generateUniqueSku($businessId = null): string
    {
        do {
            $sku = 'SKU-'.strtoupper(Str::random(8));
            if ($businessId) {
                $exists = Product::where('business_id', $businessId)->where('sku', $sku)->exists();
            } else {
                $exists = Product::where('sku', $sku)->exists();
            }
        } while ($exists);

        return $sku;
    }
}
