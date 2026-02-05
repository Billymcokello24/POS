<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice - {{ $invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.4; }
        .container { padding: 20px; max-width: 100%; margin: 0 auto; }

        .header { border-bottom: 3px solid #F59E0B; padding-bottom: 15px; margin-bottom: 15px; }
        .header-flex { display: table; width: 100%; }
        .header-left { display: table-cell; width: 50%; vertical-align: top; }
        .header-right { display: table-cell; width: 50%; text-align: right; vertical-align: top; }
        .company-name { font-size: 20px; font-weight: bold; color: #1F2937; margin-bottom: 5px; }
        .company-details { font-size: 10px; color: #6B7280; line-height: 1.5; }

        /* DUE Badge */
        .status-badge { display: inline-block; background: #F59E0B; color: white; padding: 8px 20px;
                        font-size: 18px; font-weight: bold; border-radius: 6px; margin-top: 5px; }

        .doc-info { background: #FEF3C7; padding: 12px; border-radius: 6px; margin-bottom: 15px; }
        .doc-title { font-size: 18px; font-weight: bold; color: #92400E; margin-bottom: 8px; }
        .doc-details { display: table; width: 100%; }
        .doc-detail-row { display: table-row; }
        .doc-detail-label { display: table-cell; padding: 3px 0; font-weight: 600; color: #92400E; width: 120px; font-size: 10px; }
        .doc-detail-value { display: table-cell; padding: 3px 0; color: #1F2937; font-size: 10px; }

        .urgency-note { background: #FEE2E2; border-left: 3px solid #DC2626; padding: 10px;
                        margin-bottom: 15px; color: #991B1B; font-weight: 600; border-radius: 4px; font-size: 10px; }

        .bill-to { margin-bottom: 15px; }
        .section-title { font-size: 10px; font-weight: bold; text-transform: uppercase; color: #6B7280;
                         letter-spacing: 0.5px; margin-bottom: 8px; }
        .bill-to-name { font-size: 14px; font-weight: bold; color: #1F2937; margin-bottom: 5px; }

        .payment-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .payment-table thead { background: #F59E0B; color: white; }
        .payment-table th { padding: 8px; text-align: left; font-weight: 600; font-size: 10px; }
        .payment-table td { padding: 8px; border-bottom: 1px solid #E5E7EB; font-size: 10px; }

        .total-section { background: #FEF3C7; padding: 12px; border-radius: 6px; margin-top: 15px; }
        .total-row { display: table; width: 100%; margin-bottom: 6px; }
        .total-label { display: table-cell; text-align: right; padding-right: 15px; font-weight: 600; color: #92400E; font-size: 11px; }
        .total-value { display: table-cell; text-align: right; font-weight: bold; font-size: 14px; width: 150px; }
        .grand-total { font-size: 18px; color: #F59E0B; }

        .payment-instructions { background: #DBEAFE; padding: 12px; border-radius: 6px; margin-top: 15px; }
        .payment-title { font-size: 12px; font-weight: bold; color: #1E40AF; margin-bottom: 8px; }
        .payment-steps { line-height: 1.6; color: #1E3A8A; font-size: 10px; }

        .footer { margin-top: 20px; padding-top: 15px; border-top: 2px solid #E5E7EB; text-align: center;
                  font-size: 9px; color: #6B7280; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="container">
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

        <div class="doc-info">
            <div class="doc-title">{{ $title }}</div>
            <div class="doc-details">
                <div class="doc-detail-row">
                    <div class="doc-detail-label">Invoice Number:</div>
                    <div class="doc-detail-value">{{ $invoice_number }}</div>
                </div>
                <div class="doc-detail-row">
                    <div class="doc-detail-label">Invoice Date:</div>
                    <div class="doc-detail-value">{{ $date }}</div>
                </div>
                <div class="doc-detail-row">
                    <div class="doc-detail-label">Due Date:</div>
                    <div class="doc-detail-value">{{ $due_date }}</div>
                </div>
            </div>
        </div>

        <div class="urgency-note">
            ⚠️ <strong>URGENT:</strong> Your subscription expires {{ $expiry_message }}.
            Please renew to avoid service interruption.
        </div>

        <div class="bill-to">
            <div class="section-title">Bill To</div>
            <div class="bill-to-name">{{ $business['name'] }}</div>
            {{ $business['address'] }}<br>
            Email: {{ $business['email'] }}<br>
            Phone: {{ $business['phone'] }}
        </div>

        <table class="payment-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Billing Cycle</th>
                    <th>Next Period</th>
                    <th style="text-align: right;">Amount Due</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>{{ $subscription['plan_name'] }} Subscription Renewal</strong><br>
                        <span style="font-size: 12px; color: #6B7280;">Renewal of subscription service</span>
                    </td>
                    <td>{{ $subscription['billing_cycle'] }}</td>
                    <td>
                        {{ $subscription['renewal_starts_at'] }}<br>
                        <span style="font-size: 12px; color: #6B7280;">to</span><br>
                        {{ $subscription['renewal_ends_at'] }}
                    </td>
                    <td style="text-align: right;">
                        <strong>{{ $subscription['currency'] }} {{ $subscription['amount'] }}</strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="total-section">
            <div class="total-row">
                <div class="total-label">Subtotal:</div>
                <div class="total-value">{{ $subscription['currency'] }} {{ $subscription['subtotal'] }}</div>
            </div>
            <div class="total-row">
                <div class="total-label">VAT ({{ $subscription['vat_rate'] }}%):</div>
                <div class="total-value">{{ $subscription['currency'] }} {{ $subscription['vat'] }}</div>
            </div>
            <div class="total-row" style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #F59E0B;">
                <div class="total-label">Amount Due (incl. VAT):</div>
                <div class="total-value grand-total">{{ $subscription['currency'] }} {{ $subscription['amount'] }}</div>
            </div>
        </div>

        <div class="payment-instructions">
            <div class="payment-title">How to Renew Your Subscription</div>
            <div class="payment-steps">
                1. Log in to your account at <strong>{{ $company['website'] }}</strong><br>
                2. Click on "<strong>Subscription</strong>" in the menu<br>
                3. Select your plan and billing cycle<br>
                4. Complete payment via M-PESA<br>
                5. Your subscription will be automatically renewed
            </div>
        </div>

        <div class="footer">
            <div>Questions? Contact us at {{ $company['email'] }} or {{ $company['phone'] }}</div>
            <div style="margin-top: 10px; font-style: italic;">
                This invoice is automatically generated. Please renew before {{ $due_date }} to continue service.
            </div>
        </div>
    </div>
</body>
</html>
