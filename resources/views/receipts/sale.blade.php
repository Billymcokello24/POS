<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt #{{ $sale->sale_number }}</title>
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
                        <td class="text-right">${{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-right">${{ number_format($item->total, 2) }}</td>
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

        <!-- Totals -->
        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>${{ number_format($sale->subtotal, 2) }}</span>
            </div>
            @php
                // Ensure we have VAT calculation
                $vatAmount = $sale->tax_amount > 0 ? $sale->tax_amount : ($sale->subtotal * 0.16);
                $subtotalBeforeVAT = $sale->subtotal / 1.16; // Base price before VAT
                $actualVAT = $sale->subtotal - $subtotalBeforeVAT;
            @endphp
            <div class="total-row">
                <span>VAT (16%):</span>
                <span>${{ number_format($vatAmount, 2) }}</span>
            </div>
            @if($sale->discount_amount > 0)
            <div class="total-row">
                <span>Discount:</span>
                <span>-${{ number_format($sale->discount_amount, 2) }}</span>
            </div>
            @endif
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span>${{ number_format($sale->total, 2) }}</span>
            </div>
        </div>

        <!-- VAT Breakdown -->
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ccc;">
            <div style="font-size: 10px; text-align: center; color: #666;">
                <strong>VAT BREAKDOWN (16%)</strong>
            </div>
            <div style="font-size: 10px; margin-top: 5px;">
                <div style="display: flex; justify-content: space-between; margin: 3px 0;">
                    <span>Net Amount (excl. VAT):</span>
                    <span>${{ number_format($subtotalBeforeVAT, 2) }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin: 3px 0;">
                    <span>VAT Amount (16%):</span>
                    <span>${{ number_format($actualVAT, 2) }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin: 3px 0; font-weight: bold;">
                    <span>Gross Amount (incl. VAT):</span>
                    <span>${{ number_format($sale->subtotal, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Payments -->
        <div class="payments">
            <strong>Payment Details:</strong>
            @foreach($sale->payments as $payment)
            <div class="payment-row">
                <span>{{ ucfirst($payment->payment_method) }}:</span>
                <span>${{ number_format($payment->amount, 2) }}</span>
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
                <span>${{ number_format($change, 2) }}</span>
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

