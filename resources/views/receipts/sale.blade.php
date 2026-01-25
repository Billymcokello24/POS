<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt #{{ $sale->sale_number }}</title>
    @php
        // Get currency from business or default to KES
        $currency = $sale->business->currency ?? 'KES';

        // Currency symbols mapping
        $currencySymbols = [
            'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥', 'CNY' => '¥',
            'INR' => '₹', 'KES' => 'KSh', 'TZS' => 'TSh', 'UGX' => 'USh', 'ZAR' => 'R', 'NGN' => '₦',
        ];

        $currencySymbol = $currencySymbols[$currency] ?? $currency . ' ';

        // VAT rate (could be from config or business settings)
        $vatRate = 0.16; // 16% VAT

        // Calculate VAT-inclusive breakdown
        // Since prices are VAT-inclusive, we reverse-calculate:
        // Total (VAT incl.) = Net + VAT
        // Net = Total / (1 + VAT rate)
        // VAT = Total - Net
        $grossTotal = $sale->total;
        $netAmount = $grossTotal / (1 + $vatRate);
        $vatAmount = $grossTotal - $netAmount;

        // Helper function for formatting
        $formatCurrency = function($amount) use ($currencySymbol) {
            return $currencySymbol . number_format($amount, 2);
        };
    @endphp
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            padding: 20px;
            max-width: 400px;
            margin: 0 auto;
        }

        .receipt {
            border: 2px solid #000;
            padding: 20px;
        }

        .header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }

        .header h1 {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .header p {
            margin: 3px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
        }

        .items {
            margin: 20px 0;
        }

        .items table {
            width: 100%;
            border-collapse: collapse;
        }

        .items th {
            text-align: left;
            border-bottom: 1px solid #000;
            padding: 8px 0;
        }

        .items td {
            padding: 8px 0;
            border-bottom: 1px dashed #ccc;
        }

        .items .item-name {
            font-weight: bold;
        }

        .items .text-right {
            text-align: right;
        }

        .totals {
            margin-top: 20px;
            border-top: 2px solid #000;
            padding-top: 10px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            font-size: 14px;
        }

        .total-row.grand-total {
            font-size: 18px;
            font-weight: bold;
            border-top: 2px double #000;
            padding-top: 10px;
            margin-top: 10px;
        }

        .payments {
            margin: 20px 0;
            border-top: 2px dashed #000;
            padding-top: 15px;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            border-top: 2px dashed #000;
            padding-top: 15px;
        }

        .footer p {
            margin: 5px 0;
        }

        .barcode {
            text-align: center;
            margin: 15px 0;
            font-size: 24px;
            letter-spacing: 3px;
            font-weight: bold;
        }

        .vat-box {
            margin-top: 15px;
            padding: 10px;
            border: 1px solid #000;
            background-color: #f9f9f9;
        }

        .vat-box-title {
            font-size: 11px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 8px;
            text-decoration: underline;
        }

        .vat-row {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
            font-size: 11px;
        }

        .vat-row.total {
            font-weight: bold;
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 5px;
        }

        @media print {
            body {
                padding: 0;
            }

            .receipt {
                border: none;
            }
        }
    </style>
</head>
<body>
<div class="receipt">
    <!-- Header -->
    <div class="header">
        <h1>{{ $sale->business->name ?? 'POS System' }}</h1>
        @if(isset($sale->business))
            <p>{{ $sale->business->address }}</p>
            <p>{{ $sale->business->phone }}</p>
            <p>{{ $sale->business->email }}</p>
        @endif
    </div>

    <!-- Receipt Info -->
    <div class="info-row">
        <strong>Receipt #:</strong>
        <span>{{ $sale->sale_number }}</span>
    </div>
    <div class="info-row">
        <strong>Date:</strong>
        <span>{{ $sale->created_at->format('Y-m-d H:i:s') }}</span>
    </div>
    <div class="info-row">
        <strong>Cashier:</strong>
        <span>{{ $sale->cashier->name ?? 'N/A' }}</span>
    </div>
    @if($sale->customer)
        <div class="info-row">
            <strong>Customer:</strong>
            <span>{{ $sale->customer->name }}</span>
        </div>
    @endif

    <!-- Items -->
    <div class="items">
        <table>
            <thead>
            <tr>
                <th>Item</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Price</th>
                <th class="text-right">Total</th>
            </tr>
            </thead>
            <tbody>
            @foreach($sale->items as $item)
                <tr>
                    <td class="item-name">{{ $item->product_name }}</td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">{{ $formatCurrency($item->unit_price) }}</td>
                    <td class="text-right">{{ $formatCurrency($item->total) }}</td>
                </tr>
                <tr>
                    <td colspan="4" style="font-size: 10px; color: #666;">
                        SKU: {{ $item->product_sku }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <!-- VAT Breakdown Box (VAT Inclusive) -->
    <div class="vat-box">
        <div class="vat-box-title">VAT BREAKDOWN ({{ $vatRate * 100 }}% INCLUSIVE)</div>
        <div class="vat-row">
            <span>Subtotal (excl. VAT):</span>
            <span>{{ $formatCurrency($netAmount) }}</span>
        </div>
        <div class="vat-row">
            <span>VAT ({{ $vatRate * 100 }}%):</span>
            <span>{{ $formatCurrency($vatAmount) }}</span>
        </div>
        <div class="vat-row total">
            <span>TOTAL (incl. VAT):</span>
            <span>{{ $formatCurrency($grossTotal) }}</span>
        </div>
    </div>

    <!-- Totals -->
    <div class="totals">
        @if($sale->discount_amount > 0)
            <div class="total-row">
                <span>Discount:</span>
                <span>-{{ $formatCurrency($sale->discount_amount) }}</span>
            </div>
        @endif
        <div class="total-row grand-total">
            <span>TOTAL:</span>
            <span>{{ $formatCurrency($sale->total) }}</span>
        </div>
    </div>

    <!-- Payments -->
    <div class="payments">
        <strong>Payment Details:</strong>
        @foreach($sale->payments as $payment)
            <div class="payment-row">
                <span>{{ ucfirst($payment->payment_method) }}:</span>
                <span>{{ $formatCurrency($payment->amount) }}</span>
            </div>
            @if($payment->reference_number)
                <div class="payment-row" style="font-size: 10px;">
                    <span>Ref:</span>
                    <span>{{ $payment->reference_number }}</span>
                </div>
            @endif
        @endforeach

        @php
            $totalPaid = $sale->payments->sum('amount');
            $change = $totalPaid - $sale->total;
        @endphp

        @if($change > 0)
            <div class="payment-row" style="font-weight: bold; margin-top: 10px;">
                <span>Change:</span>
                <span>{{ $formatCurrency($change) }}</span>
            </div>
        @endif
    </div>

    <!-- Barcode -->
    <div class="barcode">
        *{{ $sale->sale_number }}*
    </div>

    <!-- Footer -->
    <div class="footer">
        <p><strong>Thank you for your business!</strong></p>
        <p>Please come again</p>
        @if(isset($sale->business))
            <p style="margin-top: 10px; font-size: 10px;">
                {{ $sale->business->receipt_footer ?? 'Powered by POS System' }}
            </p>
        @endif
    </div>
</div>
</body>
</html>
