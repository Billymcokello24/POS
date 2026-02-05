<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Receipt - {{ $receipt_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.4; }
        .container { padding: 20px; max-width: 100%; margin: 0 auto; }

        /* Header */
        .header { border-bottom: 3px solid #4F46E5; padding-bottom: 15px; margin-bottom: 15px; }
        .header-flex { display: table; width: 100%; }
        .header-left { display: table-cell; width: 50%; vertical-align: top; }
        .header-right { display: table-cell; width: 50%; text-align: right; vertical-align: top; }
        .company-name { font-size: 20px; font-weight: bold; color: #1F2937; margin-bottom: 5px; }
        .company-details { font-size: 10px; color: #6B7280; line-height: 1.5; }

        /* PAID Badge */
        .status-badge { display: inline-block; background: #10B981; color: white; padding: 8px 20px;
                        font-size: 18px; font-weight: bold; border-radius: 6px; margin-top: 5px; }

        /* Document Info */
        .doc-info { background: #F9FAFB; padding: 12px; border-radius: 6px; margin-bottom: 15px; }
        .doc-title { font-size: 18px; font-weight: bold; color: #1F2937; margin-bottom: 8px; }
        .doc-details { display: table; width: 100%; }
        .doc-detail-row { display: table-row; }
        .doc-detail-label { display: table-cell; padding: 3px 0; font-weight: 600; color: #6B7280; width: 120px; font-size: 10px; }
        .doc-detail-value { display: table-cell; padding: 3px 0; color: #1F2937; font-size: 10px; }

        /* Bill To Section */
        .bill-to { margin-bottom: 15px; }
        .section-title { font-size: 10px; font-weight: bold; text-transform: uppercase; color: #6B7280;
                         letter-spacing: 0.5px; margin-bottom: 8px; }
        .bill-to-details { line-height: 1.5; font-size: 10px; }
        .bill-to-name { font-size: 14px; font-weight: bold; color: #1F2937; margin-bottom: 5px; }

        /* Payment Details Table */
        .payment-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .payment-table thead { background: #4F46E5; color: white; }
        .payment-table th { padding: 8px; text-align: left; font-weight: 600; font-size: 10px;
                            text-transform: uppercase; letter-spacing: 0.3px; }
        .payment-table td { padding: 8px; border-bottom: 1px solid #E5E7EB; font-size: 10px; }
        .payment-table tr:last-child td { border-bottom: none; }

        /* Total Section */
        .total-section { background: #F9FAFB; padding: 12px; border-radius: 6px; margin-top: 15px; }
        .total-row { display: table; width: 100%; margin-bottom: 6px; }
        .total-label { display: table-cell; text-align: right; padding-right: 15px; font-weight: 600; color: #6B7280; font-size: 11px; }
        .total-value { display: table-cell; text-align: right; font-weight: bold; font-size: 14px; width: 150px; }
        .grand-total { font-size: 18px; color: #4F46E5; }

        /* Footer */
        .footer { margin-top: 20px; padding-top: 15px; border-top: 2px solid #E5E7EB; text-align: center;
                  font-size: 9px; color: #6B7280; line-height: 1.5; }
        .footer-note { background: #FEF3C7; padding: 10px; border-radius: 6px; margin-top: 10px;
                       color: #92400E; font-style: italic; font-size: 9px; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-flex">
                <div class="header-left">
                    <div class="company-name">{{ $company['name'] }}</div>
                    <div class="company-details">
                        {{ $company['software_name'] ?? 'ModernPOS' }} - Point of Sale System<br>
                        {{ $company['address'] }}<br>
                        Phone: {{ $company['phone'] }} | Email: {{ $company['email'] }}<br>
                        Website: {{ $company['website'] }}
                    </div>
                </div>
                <div class="header-right">
                    <div class="status-badge">{{ $status }}</div>
                </div>
            </div>
        </div>

        <!-- Document Info -->
        <div class="doc-info">
            <div class="doc-title">{{ $title }}</div>
            <div class="doc-details">
                <div class="doc-detail-row">
                    <div class="doc-detail-label">Receipt Number:</div>
                    <div class="doc-detail-value">{{ $receipt_number }}</div>
                </div>
                <div class="doc-detail-row">
                    <div class="doc-detail-label">Date Issued:</div>
                    <div class="doc-detail-value">{{ $date }}</div>
                </div>
                <div class="doc-detail-row">
                    <div class="doc-detail-label">Transaction ID:</div>
                    <div class="doc-detail-value">{{ $subscription['transaction_id'] }}</div>
                </div>
            </div>
        </div>

        <!-- Bill To -->
        <div class="bill-to">
            <div class="section-title">Bill To</div>
            <div class="bill-to-details">
                <div class="bill-to-name">{{ $business['name'] }}</div>
                {{ $business['address'] }}<br>
                Email: {{ $business['email'] }}<br>
                Phone: {{ $business['phone'] }}
            </div>
        </div>

        <!-- Payment Details -->
        <table class="payment-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Billing Cycle</th>
                    <th>Period</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>{{ $subscription['plan_name'] }} Subscription</strong><br>
                        <span style="font-size: 12px; color: #6B7280;">Subscription Service</span>
                    </td>
                    <td>{{ $subscription['billing_cycle'] }}</td>
                    <td>
                        {{ $subscription['starts_at'] }}<br>
                        <span style="font-size: 12px; color: #6B7280;">to</span><br>
                        {{ $subscription['ends_at'] }}
                    </td>
                    <td style="text-align: right;">
                        <strong>{{ $subscription['currency'] }} {{ $subscription['amount'] }}</strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Total Section -->
        <div class="total-section">
            <div class="total-row">
                <div class="total-label">Subtotal:</div>
                <div class="total-value">{{ $subscription['currency'] }} {{ $subscription['subtotal'] }}</div>
            </div>
            <div class="total-row">
                <div class="total-label">VAT ({{ $subscription['vat_rate'] }}%):</div>
                <div class="total-value">{{ $subscription['currency'] }} {{ $subscription['vat'] }}</div>
            </div>
            <div class="total-row" style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #E5E7EB;">
                <div class="total-label">Total Paid (incl. VAT):</div>
                <div class="total-value grand-total">{{ $subscription['currency'] }} {{ $subscription['amount'] }}</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div>Thank you for your business!</div>
            <div>If you have any questions about this receipt, please contact us at {{ $company['email'] }}</div>
            <div class="footer-note">
                This is an automatically generated receipt. Payment was received via {{ $subscription['payment_method'] }}.
            </div>
        </div>
    </div>
</body>
</html>
