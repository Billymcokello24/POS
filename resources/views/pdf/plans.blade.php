<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Subscription Plans</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 14px; color: #333; line-height: 1.6; }
        .container { padding: 40px; }

        .header { text-align: center; margin-bottom: 40px; padding-bottom: 30px; border-bottom: 4px solid #6366F1; }
        .title { font-size: 36px; font-weight: bold; color: #1F2937; margin-bottom: 10px; }
        .subtitle { font-size: 16px; color: #6B7280; }

        .plan-card { border: 2px solid #E5E7EB; border-radius: 12px; padding: 25px; margin-bottom: 30px;
                     page-break-inside: avoid; }
        .plan-header { background: linear-gradient(135deg, #6366F1, #8B5CF6); color: white; padding: 20px;
                       margin: -25px -25px 20px -25px; border-radius: 10px 10px 0 0; }
        .plan-name { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
        .plan-category { font-size: 14px; opacity: 0.9; }
        .plan-pricing { background: #F9FAFB; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .price-row { display: table; width: 100%; margin-bottom: 10px; }
        .price-label { display: table-cell; font-weight: 600; color: #6B7280; }
        .price-value { display: table-cell; text-align: right; font-size: 20px; font-weight: bold; color: #1F2937; }

        .features { margin-top: 20px; }
        .features-title { font-size: 14px; font-weight: bold; text-transform: uppercase; color: #6B7280;
                         margin-bottom: 12px; letter-spacing: 1px; }
        .feature-item { padding: 8px 0; border-bottom: 1px dotted #E5E7EB; }
        .feature-item:before { content: "✓ "; color: #10B981; font-weight: bold; margin-right: 8px; }

        .cta { background: #DBEAFE; padding: 30px; border-radius: 12px; text-align: center; margin-top: 40px; }
        .cta-title { font-size: 20px; font-weight: bold; color: #1E40AF; margin-bottom: 15px; }
        .cta-text { color: #1E3A8A; margin-bottom: 20px; line-height: 1.8; }
        .cta-steps { text-align: left; max-width: 600px; margin: 0 auto; line-height: 2; color: #1E3A8A; }

        .footer { text-align: center; margin-top: 40px; padding-top: 30px; border-top: 2px solid #E5E7EB;
                  font-size: 12px; color: #6B7280; line-height: 1.8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="title">{{ $company['software_name'] ?? 'ModernPOS' }} Subscription Plans</div>
            <div class="subtitle">Choose the perfect plan for your business</div>
            <div style="margin-top: 15px; font-size: 13px; color: #6B7280;">
                <strong>{{ $company['name'] }}</strong><br>
                {{ $company['address'] }} | {{ $company['phone'] }}<br>
                {{ $company['email'] }} | {{ $company['website'] }}
            </div>
            <div style="margin-top: 10px; font-size: 12px; color: #9CA3AF;">{{ $date }}</div>
        </div>

        @foreach($plans as $plan)
        <div class="plan-card">
            <div class="plan-header">
                <div class="plan-name">{{ $plan->name }}</div>
                <div class="plan-category">{{ $plan->size_category }} Business Plan</div>
            </div>

            <div class="plan-pricing">
                <div class="price-row">
                    <div class="price-label">Monthly:</div>
                    <div class="price-value">KES {{ number_format($plan->price_monthly, 2) }}/mo</div>
                </div>
                <div class="price-row">
                    <div class="price-label">Yearly:</div>
                    <div class="price-value">KES {{ number_format($plan->price_yearly, 2) }}/yr</div>
                </div>
                <div style="margin-top: 10px; font-size: 12px; color: #6B7280; text-align: right;">
                    Save {{ round((1 - ($plan->price_yearly / ($plan->price_monthly * 12))) * 100) }}% with yearly billing
                </div>
            </div>

            <div class="features">
                <div class="features-title">Features Included</div>
                @foreach($plan->features as $feature)
                <div class="feature-item">{{ $feature->name }}</div>
                @endforeach
            </div>
        </div>
        @endforeach

        <div class="cta">
            <div class="cta-title">Ready to Get Started?</div>
            <div class="cta-text">
                Choose your plan and start growing your business today!
            </div>
            <div class="cta-steps">
                <strong>How to Subscribe:</strong><br>
                1. Log in to your account at <strong>{{ $company['website'] }}</strong><br>
                2. Click on "<strong>Subscription</strong>" in the menu<br>
                3. Choose your preferred plan and billing cycle<br>
                4. Complete payment via M-PESA<br>
                5. Start enjoying all premium features immediately!
            </div>
        </div>

        <div class="footer">
            <div><strong>{{ $company['name'] }}</strong></div>
            <div>{{ $company['address'] }}</div>
            <div>Phone: {{ $company['phone'] }} | Email: {{ $company['email'] }}</div>
            <div>Website: {{ $company['website'] }}</div>
        </div>
    </div>
</body>
</html>
