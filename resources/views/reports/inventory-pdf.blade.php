<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Inventory Report - {{ $business->name ?? 'POS System' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        .business-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .business-name {
            font-size: 32px;
            font-weight: bold;
            margin: 0 0 10px 0;
        }
        .business-details {
            font-size: 14px;
            line-height: 1.6;
            opacity: 0.95;
        }
        .business-details div {
            margin: 5px 0;
        }
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 20px;
            background: #f0fdf4;
            border-left: 5px solid #10b981;
        }
        .report-title {
            font-size: 28px;
            font-weight: bold;
            color: #1e293b;
            margin: 0;
        }
        .report-info {
            text-align: right;
            font-size: 13px;
            color: #64748b;
        }
        h1 {
            color: #10b981;
            border-bottom: 3px solid #10b981;
            padding-bottom: 10px;
        }
        .summary {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            background: #f3f4f6;
            padding: 20px;
            border-radius: 8px;
        }
        .summary-card {
            text-align: center;
            padding: 15px;
        }
        .summary-card h3 {
            margin: 0;
            font-size: 14px;
            color: #6b7280;
        }
        .summary-card p {
            margin: 10px 0 0 0;
            font-size: 24px;
            font-weight: bold;
            color: #10b981;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th {
            background-color: #10b981;
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 12px;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 11px;
        }
        tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .text-right {
            text-align: right;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin-top: 30px;
            margin-bottom: 15px;
            color: #1f2937;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
        }
        .alert {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 15px;
            margin: 20px 0;
        }
        .alert h3 {
            color: #dc2626;
            margin: 0 0 10px 0;
        }
        .page-number {
            position: fixed;
            bottom: 20px;
            right: 20px;
            font-size: 11px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <!-- Business Header -->
    <div class="business-header">
        <div class="business-name">{{ $business->name ?? 'POS System' }}</div>
        <div class="business-details">
            @if($business->address)
                <div>📍 {{ $business->address }}</div>
            @endif
            @if($business->phone)
                <div>📞 {{ $business->phone }}</div>
            @endif
            @if($business->email)
                <div>📧 {{ $business->email }}</div>
            @endif
            @if($business->tax_id)
                <div>🏢 Tax ID: {{ $business->tax_id }}</div>
            @endif
        </div>
    </div>

    <!-- Report Header -->
    <div class="report-header">
        <div>
            <div class="report-title">INVENTORY REPORT</div>
            <div style="color: #64748b; margin-top: 5px;">Current Stock Status</div>
        </div>
        <div class="report-info">
            <div><strong>Report ID:</strong> IR-{{ now()->format('YmdHis') }}</div>
            <div><strong>Generated:</strong> {{ now()->format('M d, Y @ g:i A') }}</div>
            <div><strong>Currency:</strong> {{ $business->currency ?? 'USD' }}</div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary">
        <div class="summary-card">
            <h3>Total Inventory Value</h3>
            <p>{{ $business->currency ?? 'KSh' }} {{ number_format($totalValue, 2) }}</p>
        </div>
        <div class="summary-card">
            <h3>Total Products</h3>
            <p>{{ $totalProducts }}</p>
        </div>
        <div class="summary-card">
            <h3>Low Stock Items</h3>
            <p style="color: #ef4444;">{{ $lowStockCount }}</p>
        </div>
    </div>

    @if($lowStockCount > 0)
    <div class="alert">
        <h3>🚨 Low Stock Alert</h3>
        <p>{{ $lowStockCount }} items need immediate restocking attention!</p>
    </div>
    @endif

    <div class="section-title">Inventory Value by Category</div>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th class="text-right">Product Count</th>
                <th class="text-right">Total Value</th>
                <th class="text-right">Percentage</th>
            </tr>
        </thead>
        <tbody>
            @foreach($valueByCategory as $category)
            <tr>
                <td>{{ $category->category }}</td>
                <td class="text-right">{{ $category->product_count }}</td>
                <td class="text-right">{{ $business->currency ?? 'KSh' }} {{ number_format($category->value, 2) }}</td>
                <td class="text-right">{{ number_format(($category->value / $totalValue) * 100, 1) }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>
        </thead>
        <tbody>
            @foreach($valueByCategory as $category)
            <tr>
                <td>{{ $category->category }}</td>
                <td class="text-right">{{ $category->product_count }}</td>
                <td class="text-right">${{ number_format($category->value, 2) }}</td>
                <td class="text-right">{{ number_format(($category->value / $totalValue) * 100, 1) }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if($lowStockItems->count() > 0)
    <div class="section-title">Low Stock Items</div>
    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Product Name</th>
                <th>Category</th>
                <th class="text-right">Current Stock</th>
                <th class="text-right">Reorder Level</th>
                <th class="text-right">Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lowStockItems as $product)
            <tr>
                <td>{{ $product->sku }}</td>
                <td>{{ $product->name }}</td>
                <td>{{ $product->category->name ?? 'Uncategorized' }}</td>
                <td class="text-right" style="color: #dc2626; font-weight: bold;">{{ $product->quantity }}</td>
                <td class="text-right">{{ $product->reorder_level }}</td>
                <td class="text-right">{{ $business->currency ?? 'KSh' }} {{ number_format($product->quantity * $product->cost_price, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="section-title">Complete Inventory List</div>
    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Product Name</th>
                <th>Category</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Cost Price</th>
                <th class="text-right">Selling Price</th>
                <th class="text-right">Inventory Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach($inventoryStatus->take(50) as $product)
            <tr>
                <td>{{ $product->sku }}</td>
                <td>{{ $product->name }}</td>
                <td>{{ $product->category->name ?? 'Uncategorized' }}</td>
                <td class="text-right">{{ $product->quantity }}</td>
                <td class="text-right">{{ $business->currency ?? 'KSh' }} {{ number_format($product->cost_price, 2) }}</td>
                <td class="text-right">{{ $business->currency ?? 'KSh' }} {{ number_format($product->selling_price, 2) }}</td>
                <td class="text-right">{{ $business->currency ?? 'KSh' }} {{ number_format($product->inventory_value, 2) }}</td>
            </tr>
            @endforeach
            @if($inventoryStatus->count() > 50)
            <tr>
                <td colspan="7" style="text-align: center; font-style: italic; color: #6b7280;">
                    Showing first 50 items of {{ $inventoryStatus->count() }} total products
                </td>
            </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        <div style="color: #94a3b8;">
            This report was automatically generated by {{ $business->name ?? 'POS System' }} on {{ now()->format('F j, Y @ g:i A') }}
        </div>
    </div>

    <div class="page-number">
        Page 1 of 1
    </div>
</body>
</html>

