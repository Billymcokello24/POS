<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Report - {{ $business->name ?? 'POS System' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        .business-header {
            background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
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
            background: #f8fafc;
            border-left: 5px solid #2563eb;
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
            color: #2563eb;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 10px;
        }
        .period {
            background: #f3f4f6;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #2563eb;
        }
        .summary {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
        }
        .summary-card {
            text-align: center;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
            min-width: 150px;
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
            color: #2563eb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th {
            background-color: #2563eb;
            color: white;
            padding: 12px;
            text-align: left;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
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
            <div class="report-title">📊 SALES REPORT</div>
            <div style="color: #64748b; margin-top: 5px;">Period: {{ $startDate }} to {{ $endDate }}</div>
        </div>
        <div class="report-info">
            <div><strong>Report ID:</strong> SR-{{ now()->format('YmdHis') }}</div>
            <div><strong>Generated:</strong> {{ now()->format('M d, Y @ g:i A') }}</div>
            <div><strong>Currency:</strong> {{ $business->currency ?? 'USD' }}</div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary">
        <div class="summary-card">
            <h3>Total Revenue</h3>
            <p>{{ $business->currency ?? 'KSh' }} {{ number_format($totalRevenue, 2) }}</p>
        </div>
        <div class="summary-card">
            <h3>Total Orders</h3>
            <p>{{ $totalOrders }}</p>
        </div>
        <div class="summary-card">
            <h3>Tax Collected</h3>
            <p>{{ $business->currency ?? 'KSh' }} {{ number_format($totalTax, 2) }}</p>
        </div>
    </div>

    <div class="section-title">Daily Sales Breakdown</div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th class="text-right">Orders</th>
                <th class="text-right">Subtotal</th>
                <th class="text-right">Tax</th>
                <th class="text-right">Discount</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($salesData as $day)
            <tr>
                <td>{{ $day->date }}</td>
                <td class="text-right">{{ $day->total_orders }}</td>
                <td class="text-right">{{ $business->currency ?? 'KSh' }} {{ number_format($day->subtotal, 2) }}</td>
                <td class="text-right">{{ $business->currency ?? 'KSh' }} {{ number_format($day->tax, 2) }}</td>
                <td class="text-right">{{ $business->currency ?? 'KSh' }} {{ number_format($day->discount, 2) }}</td>
                <td class="text-right"><strong>{{ $business->currency ?? 'KSh' }} {{ number_format($day->total, 2) }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">Top Selling Products</div>
    <table>
        <thead>
            <tr>
                <th>Rank</th>
                <th>Product Name</th>
                <th class="text-right">Quantity Sold</th>
                <th class="text-right">Revenue</th>
            </tr>
        </thead>
        <tbody>
            @foreach($topProducts as $index => $product)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $product->product_name }}</td>
                <td class="text-right">{{ $product->total_quantity }}</td>
                <td class="text-right"><strong>{{ $business->currency ?? 'KSh' }} {{ number_format($product->total_revenue, 2) }}</strong></td>
            </tr>
            @endforeach
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

