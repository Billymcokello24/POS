<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\TaxConfiguration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Milon\Barcode\Facades\DNS1DFacade;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel;
use Illuminate\Support\Collection;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        if (!auth()->user()->hasPermission('view_products')) {
            abort(403, 'Unauthorized');
        }
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
        if (!auth()->user()->hasPermission('create_products')) {
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
        if (!auth()->user()->hasPermission('create_products')) {
            abort(403, 'Unauthorized');
        }
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

        // Barcode must be entered manually - no auto-generation
        // Set default barcode_type if barcode is provided without type
        if (!empty($validated['barcode']) && empty($validated['barcode_type'])) {
            $validated['barcode_type'] = 'CODE128';
        }

        // Plan Limit Check
        $business = \App\Models\Business::find($businessId);
        if ($business && !$business->withinPlanLimits('products')) {
            return back()->with('error', 'Your current plan limit for products (' . $business->plan->max_products . ') has been reached. Please upgrade to add more.');
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
        if (!auth()->user()->hasPermission('edit_products')) {
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
        if (!auth()->user()->hasPermission('edit_products')) {
            abort(403, 'Unauthorized');
        }
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
        if (!auth()->user()->hasPermission('delete_products')) {
            abort(403, 'Unauthorized');
        }
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

    public function import(Request $request)
    {
        if (!auth()->user()->hasPermission('create_products')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xls,xlsx',
        ]);

        $businessId = auth()->user()->current_business_id;

        if (!$businessId) {
            return back()->with('error', 'No active business selected for your account.');
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
                if (empty($data) || !is_array($data[0])) {
                    return back()->with('error', 'Uploaded file is empty or has invalid format.');
                }

                $headers = array_map('strtolower', array_map('trim', $data[0]));

                // Validate headers against allowed list and collect warnings for unknown columns
                $allowed = [
                    'name','product_name','sku','barcode','category','description',
                    'cost_price','cost','selling_price','price','sale_price',
                    'quantity','qty','reorder_level','reorder','min_stock','unit'
                ];
                $unknown = array_values(array_diff($headers, $allowed));
                if (!empty($unknown)) {
                    $warnings[] = 'Unknown columns ignored: ' . implode(', ', $unknown) . '. Use the template if unsure.';
                }

                // Validate headers contain at least 'name'
                if (!in_array('name', $headers) && !in_array('product_name', $headers)) {
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
                            'name','product_name','sku','barcode','category','description',
                            'cost_price','cost','selling_price','price','sale_price',
                            'quantity','qty','reorder_level','reorder','min_stock','unit'
                        ];
                        $unknown = array_values(array_diff($header, $allowed));
                        if (!empty($unknown)) {
                            // collect warning but continue parsing rows; unknown columns will be ignored
                            $warnings[] = 'Unknown columns ignored: ' . implode(', ', $unknown) . '. Use the template if unsure.';
                        }
                        // Validate headers
                        if (!in_array('name', $header) && !in_array('product_name', $header)) {
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
            return back()->with('error', 'Failed to parse uploaded file: ' . $e->getMessage());
        }

        $created = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // account for header row

            // Basic mapping and validation
            $name = $row['name'] ?? $row['product_name'] ?? null;
            if (!$name || trim($name) === '') {
                $errors[] = "Row {$rowNum}: missing required field 'name'.";
                continue;
            }

            $sku = isset($row['sku']) ? trim((string)$row['sku']) : null;
            $barcode = isset($row['barcode']) ? trim((string)$row['barcode']) : null;
            $categoryName = isset($row['category']) ? trim((string)$row['category']) : null;

            // Prices
            $costPriceRaw = $row['cost_price'] ?? $row['cost'] ?? null;
            $sellingPriceRaw = $row['selling_price'] ?? $row['price'] ?? $row['sale_price'] ?? null;
            $quantityRaw = $row['quantity'] ?? $row['qty'] ?? null;
            $reorderRaw = $row['reorder_level'] ?? $row['reorder'] ?? $row['min_stock'] ?? null;
            $unit = isset($row['unit']) ? trim((string)$row['unit']) : 'pcs';

            // Numeric parsing with validation
            $costPrice = 0.0;
            if ($costPriceRaw !== null && $costPriceRaw !== '') {
                if (!is_numeric($costPriceRaw)) {
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
                if (!is_numeric($sellingPriceRaw)) {
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
                if (!is_numeric($quantityRaw)) {
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
                if (!is_numeric($reorderRaw)) {
                    $errors[] = "Row {$rowNum}: invalid reorder_level value '{$reorderRaw}'. Must be an integer.";
                    continue;
                }
                $reorder = intval($reorderRaw);
                if ($reorder < 0) {
                    $errors[] = "Row {$rowNum}: reorder_level cannot be negative.";
                    continue;
                }
            }

            // Category
            $categoryId = null;
            if ($categoryName) {
                $cat = Category::firstOrCreate([
                    'business_id' => $businessId,
                    'name' => $categoryName,
                ], [
                    'slug' => Str::slug($categoryName),
                    'is_active' => true,
                ]);
                $categoryId = $cat->id;
            }

            if (empty($sku)) {
                $sku = $this->generateUniqueSku();
            }

            if (empty($barcode)) {
                $barcode = $this->generateUniqueBarcode();
            }

            // Check uniqueness
            if (Product::where('business_id', $businessId)->where('sku', $sku)->exists()) {
                $errors[] = "Row {$rowNum}: SKU '{$sku}' already exists for this business.";
                continue;
            }
            if (Product::where('business_id', $businessId)->where('barcode', $barcode)->exists()) {
                $errors[] = "Row {$rowNum}: Barcode '{$barcode}' already exists for this business.";
                continue;
            }

            try {
                $product = Product::create([
                    'business_id' => $businessId,
                    'name' => $name,
                    'sku' => $sku,
                    'barcode' => $barcode,
                    'description' => $row['description'] ?? null,
                    'category_id' => $categoryId,
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
                $errors[] = "Row {$rowNum}: failed to create product - " . $e->getMessage();
                continue;
            }
        }

        $message = "Imported {$created} products.";
        if (!empty($errors)) {
            $message .= ' ' . count($errors) . ' rows had errors.';
        }

        $flash = ['success' => $message, 'import_errors' => $errors];
        if (!empty($warnings)) {
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
            'name', 'sku', 'barcode', 'category', 'description', 'cost_price', 'selling_price', 'quantity', 'reorder_level', 'unit'
        ];

        $callback = function() use ($columns) {
            $out = fopen('php://output', 'w');
            // Header
            fputcsv($out, $columns);
            // Example row
            fputcsv($out, [
                'Tea Green Organic', '', '', 'Beverages', 'Organic green tea 100g', '8.00', '12.00', '20', '5', 'pcs'
            ]);
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function generateUniqueBarcode(): string
    {
        do {
            $barcode = str_pad(rand(0, 999999999999), 12, '0', STR_PAD_LEFT);
        } while (Product::where('barcode', $barcode)->exists());

        return $barcode;
    }

    private function generateUniqueSku(): string
    {
        do {
            $sku = 'SKU-' . strtoupper(Str::random(8));
        } while (Product::where('sku', $sku)->exists());

        return $sku;
    }
}
