<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Business Report - {{ $business->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 40px;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .header .business-info {
            font-size: 12px;
            opacity: 0.95;
            margin-top: 10px;
        }

        .header .report-meta {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.3);
            font-size: 11px;
        }

        .content {
            padding: 0 40px 40px;
        }

        .section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }

        .section-title {
            background-color: #f7fafc;
            border-left: 4px solid #667eea;
            padding: 12px 15px;
            font-size: 16px;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 15px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .summary-card {
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }

        .summary-card .label {
            font-size: 10px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .summary-card .value {
            font-size: 20px;
            font-weight: bold;
            color: #2d3748;
        }

        .summary-card.profit {
            background: #f0fff4;
            border-color: #48bb78;
        }

        .summary-card.profit .value {
            color: #22543d;
        }

        .summary-card.revenue {
            background: #ebf4ff;
            border-color: #4299e1;
        }

        .summary-card.revenue .value {
            color: #2c5282;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table thead {
            background-color: #edf2f7;
        }

        table thead th {
            padding: 12px 10px;
            text-align: left;
            font-size: 10px;
            font-weight: bold;
            color: #2d3748;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #cbd5e0;
        }

        table tbody tr {
            border-bottom: 1px solid #e2e8f0;
        }

        table tbody tr:hover {
            background-color: #f7fafc;
        }

        table tbody td {
            padding: 10px;
            font-size: 11px;
            color: #4a5568;
        }

        table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .font-bold {
            font-weight: bold;
        }

        .text-green {
            color: #22543d;
        }

        .text-red {
            color: #742a2a;
        }

        .footer {
            position: fixed;
            bottom: 20px;
            left: 40px;
            right: 40px;
            text-align: center;
            font-size: 9px;
            color: #a0aec0;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
        }

        .page-break {
            page-break-after: always;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
        }

        .badge-success {
            background-color: #c6f6d5;
            color: #22543d;
        }

        .badge-warning {
            background-color: #fefcbf;
            color: #744210;
        }

        .badge-danger {
            background-color: #fed7d7;
            color: #742a2a;
        }

        .payment-method {
            padding: 10px;
            background: #f7fafc;
            border-radius: 6px;
            margin-bottom: 8px;
        }

        .payment-method .method-name {
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 3px;
        }

        .payment-method .method-stats {
            font-size: 10px;
            color: #718096;
        }

        .chart-box {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .chart-title {
            font-size: 14px;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>{{ $business->name }}</h1>
        <div class="business-info">
            @if($business->address)
                <div>{{ $business->address }}</div>
            @endif
            @if($business->phone)
                <div>Phone: {{ $business->phone }}</div>
            @endif
            @if($business->email)
                <div>Email: {{ $business->email }}</div>
            @endif
        </div>
        <div class="report-meta">
            <strong>Business Intelligence Report - {{ $period }}</strong><br>
            Period: {{ $startDate }} to {{ $endDate }}<br>
            Generated: {{ $generatedAt }}
        </div>
    </div>

    <div class="content">
        <!-- Executive Summary -->
        <div class="section">
            <div class="section-title">Executive Summary</div>

            <div class="summary-grid">
                <div class="summary-card revenue">
                    <div class="label">Total Revenue</div>
                    <div class="value">{{ $currency }} {{ number_format($summary['total_revenue'], 2) }}</div>
                </div>

                <div class="summary-card profit">
                    <div class="label">Gross Profit</div>
                    <div class="value {{ $summary['gross_profit'] >= 0 ? 'text-green' : 'text-red' }}">
                        {{ $currency }} {{ number_format($summary['gross_profit'], 2) }}
                    </div>
                </div>

                <div class="summary-card">
                    <div class="label">Profit Margin</div>
                    <div class="value">{{ number_format($summary['profit_margin'], 1) }}%</div>
                </div>

                <div class="summary-card">
                    <div class="label">Total Orders</div>
                    <div class="value">{{ number_format($summary['total_orders']) }}</div>
                </div>

                <div class="summary-card">
                    <div class="label">Avg Order Value</div>
                    <div class="value">{{ $currency }} {{ number_format($summary['average_order_value'], 2) }}</div>
                </div>

                <div class="summary-card">
                    <div class="label">Total Products</div>
                    <div class="value">{{ number_format($products->count()) }}</div>
                </div>
            </div>
        </div>

        <!-- Charts: Revenue Trend, Top Products Bar, Profit Distribution, Flowchart -->
        <div class="section">
            <div class="section-title">Visual Analytics</div>

            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                {{-- Revenue Trend & Forecast (Large) --}}
                <div style="flex:1 1 60%; min-width:320px;" class="chart-box">
                    <div class="chart-title">Revenue Trend & Forecast</div>

                    @if(!empty($historical) && count($historical) > 1)
                        @php
                            // Prepare series
                            $histValues = array_map(fn($h) => (float) ($h['revenue'] ?? 0), $historical);
                            $histLabels = array_map(fn($h) => ($h['period'] ?? ''), $historical);
                            $n = count($histValues);
                            $last = end($histValues);
                            // If forecast passed from controller as $forecasts and contains revenue projection, use it; else simple 12% projection
                            $proj = null;
                            if(!empty($forecasts) && is_array($forecasts)){
                                foreach($forecasts as $f){ if(isset($f['metric']) && strtolower($f['metric']) === 'revenue' && isset($f['projection'])){ $proj = (float) $f['projection']; break; } }
                            }
                            $forecast = $proj ?? ($last * 1.12);
                            $max = max(max($histValues), $forecast) * 1.15;

                            // Build points
                            $w = 520; $h = 180; $p = 40; $cw = $w - 2*$p; $ch = $h - 2*$p;
                            $pts = [];
                            foreach($histValues as $i => $v){
                                $x = $p + ($i * ($cw / max($n-1,1)));
                                $y = $p + $ch - (($v / $max) * $ch);
                                $pts[] = round($x,1).','.round($y,1);
                            }
                            $lx = $p + (($n-1) * ($cw / max($n-1,1)));
                            $ly = $p + $ch - (($last / $max) * $ch);
                            $fx = $lx + 60;
                            $fy = $p + $ch - (($forecast / $max) * $ch);
                        @endphp

                        <div style="overflow:hidden;">
                            <svg width="{{ $w }}" height="{{ $h }}" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <linearGradient id="areaGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                                        <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.35"/>
                                        <stop offset="100%" stop-color="#3b82f6" stop-opacity="0.02"/>
                                    </linearGradient>
                                </defs>

                                {{-- Grid lines and Y labels --}}
                                @for($i=0;$i<=4;$i++)
                                    @php $gy = $p + ($ch * $i / 4); $lbl = number_format(($max * (4 - $i) / 4) / 1000, 0) . 'K'; @endphp
                                    <line x1="{{ $p }}" y1="{{ $gy }}" x2="{{ $p + $cw + 60 }}" y2="{{ $gy }}" stroke="#e5e7eb" stroke-dasharray="4,4" />
                                    <text x="{{ $p - 6 }}" y="{{ $gy + 4 }}" font-size="8" text-anchor="end" fill="#6b7280">{{ $lbl }}</text>
                                @endfor

                                {{-- Area under curve --}}
                                <polygon points="{{ $p }},{{ $p + $ch }} {{ implode(' ', $pts) }} {{ $lx }},{{ $p + $ch }}" fill="url(#areaGrad)" />

                                {{-- Actual line --}}
                                <polyline points="{{ implode(' ', $pts) }}" fill="none" stroke="#3b82f6" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />

                                {{-- Points --}}
                                @foreach($histValues as $i => $v)
                                    @php $x = $p + ($i * ($cw / max($n-1,1))); $y = $p + $ch - (($v / $max) * $ch); @endphp
                                    <circle cx="{{ round($x,1) }}" cy="{{ round($y,1) }}" r="4" fill="#3b82f6" stroke="#fff" stroke-width="1.5" />
                                @endforeach

                                {{-- Forecast dashed line and point --}}
                                <line x1="{{ $lx }}" y1="{{ round($ly,1) }}" x2="{{ $fx }}" y2="{{ round($fy,1) }}" stroke="#ef4444" stroke-width="2" stroke-dasharray="6,4" />
                                <circle cx="{{ $fx }}" cy="{{ round($fy,1) }}" r="5" fill="#ef4444" stroke="#fff" stroke-width="1.5" />

                                {{-- X labels --}}
                                @foreach($histLabels as $i => $label)
                                    @php $x = $p + ($i * ($cw / max($n-1,1))); @endphp
                                    <text x="{{ round($x,1) }}" y="{{ $h - 8 }}" text-anchor="middle" font-size="8" fill="#6b7280">{{ $label }}</text>
                                @endforeach

                                {{-- Forecast label --}}
                                <text x="{{ $fx }}" y="{{ $h - 8 }}" text-anchor="middle" font-size="8" fill="#ef4444">Forecast</text>

                                {{-- Legend --}}
                                <g transform="translate({{ $w - 150 }}, 8)">
                                    <rect x="0" y="0" width="140" height="36" rx="4" fill="#fff" stroke="#e6e7eb" />
                                    <line x1="8" y1="12" x2="28" y2="12" stroke="#3b82f6" stroke-width="3" />
                                    <text x="34" y="15" font-size="8" fill="#374151">Actual Revenue</text>
                                    <line x1="8" y1="26" x2="28" y2="26" stroke="#ef4444" stroke-width="2" stroke-dasharray="4,3" />
                                    <text x="34" y="29" font-size="8" fill="#374151">Projected</text>
                                </g>
                            </svg>
                        </div>
                    @else
                        <div style="color:#6b7280;padding:18px;border-radius:8px;background:#f8fafc">Insufficient historical data for revenue trend chart.</div>
                    @endif
                </div>

                {{-- Right column: Bar & Pie charts --}}
                <div style="flex:1 1 36%; min-width:240px; display:flex;flex-direction:column;gap:12px;">
                    {{-- Top Products Bar Chart --}}
                    <div class="chart-box">
                        <div class="chart-title">Top Products by Revenue</div>
                        @if(!empty($topProducts) && $topProducts->count() > 0)
                            @php
                                $topArr = $topProducts->map(fn($p) => ['name'=>$p->name,'revenue'=> (float) ($p->total_revenue ?? $p->revenue ?? 0)])->toArray();
                                $maxR = max(array_column($topArr, 'revenue')) ?: 1;
                                $bw = 180; $bh = 22; $gap = 8; $hcount = count($topArr);
                            @endphp
                            <svg width="260" height="{{ max( (int)($hcount * ($bh+$gap) + 20), 120) }}" xmlns="http://www.w3.org/2000/svg">
                                @foreach($topArr as $i => $prod)
                                    @php
                                        $barW = ($prod['revenue'] / $maxR) * 150;
                                        $y = 10 + ($i * ($bh + $gap));
                                        $label = strlen($prod['name']) > 14 ? substr($prod['name'],0,14).'..' : $prod['name'];
                                    @endphp
                                    <text x="6" y="{{ $y + 14 }}" font-size="9" fill="#374151">{{ $label }}</text>
                                    <rect x="90" y="{{ $y }}" width="150" height="{{ $bh }}" fill="#f3f4f6" rx="4" />
                                    <rect x="90" y="{{ $y }}" width="{{ round($barW,1) }}" height="{{ $bh }}" fill="#3b82f6" rx="4" />
                                    <text x="{{ 95 + $barW }}" y="{{ $y + 14 }}" font-size="9" fill="#111" font-weight="bold">{{ number_format($prod['revenue']/1000,1) }}K</text>
                                @endforeach
                            </svg>
                        @else
                            <div style="color:#6b7280;padding:10px;border-radius:6px;background:#f8fafc">No product revenue data available.</div>
                        @endif
                    </div>

                    {{-- Profit Distribution Pie Chart --}}
                    <div class="chart-box">
                        <div class="chart-title">Cost & Profit Distribution</div>
                        @php
                            $totalRev = $summary['total_revenue'] ?? 0;
                            $gross = $summary['gross_profit'] ?? 0;
                            $other = max($totalRev - $gross, 0);
                        @endphp
                        @if($totalRev > 0)
                            @php
                                $data = [
                                    ['label'=>'Gross Profit','value'=>$gross,'color'=>'#10b981'],
                                    ['label'=>'Costs & Expenses','value'=>$other,'color'=>'#ef4444'],
                                ];
                                $total = $gross + $other;
                                $cx = 80; $cy = 70; $r = 60; $angle = -90;
                            @endphp
                            <svg width="240" height="160" xmlns="http://www.w3.org/2000/svg">
                                @foreach($data as $d)
                                    @if($d['value'] <= 0) @continue @endif
                                    @php
                                        $a = ($d['value'] / $total) * 360;
                                        $ea = $angle + $a;
                                        $x1 = $cx + $r * cos(deg2rad($angle));
                                        $y1 = $cy + $r * sin(deg2rad($angle));
                                        $x2 = $cx + $r * cos(deg2rad($ea));
                                        $y2 = $cy + $r * sin(deg2rad($ea));
                                        $la = $a > 180 ? 1 : 0;
                                    @endphp
                                    <path d="M {{ $cx }} {{ $cy }} L {{ round($x1,2) }} {{ round($y1,2) }} A {{ $r }} {{ $r }} 0 {{ $la }} 1 {{ round($x2,2) }} {{ round($y2,2) }} Z" fill="{{ $d['color'] }}" stroke="#fff" stroke-width="2"/>
                                    @php $angle = $ea; @endphp
                                @endforeach

                                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="32" fill="#fff" />
                                <text x="{{ $cx }}" y="{{ $cy + 4 }}" text-anchor="middle" font-size="10" font-weight="bold" fill="#374151">Total</text>

                                {{-- legend --}}
                                @php $lx = 165; $ly = 30; @endphp
                                @foreach($data as $i => $d)
                                    @php $yy = $ly + ($i * 20); $pct = $total>0?number_format(($d['value']/$total)*100,1):0; @endphp
                                    <rect x="{{ $lx }}" y="{{ $yy - 10 }}" width="12" height="12" fill="{{ $d['color'] }}" rx="2"/>
                                    <text x="{{ $lx + 18 }}" y="{{ $yy }}" font-size="9" fill="#374151">{{ $d['label'] }} ({{ $pct }}%)</text>
                                @endforeach
                            </svg>
                        @else
                            <div style="color:#6b7280;padding:10px;border-radius:6px;background:#f8fafc">No financial breakdown available.</div>
                        @endif
                    </div>

                </div>
            </div>

            {{-- Small flowchart to show BI pipeline --}}
            <div style="margin-top:12px;text-align:center;">
                <svg width="100%" height="90" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="g1" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" stop-color="#60a5fa"/><stop offset="100%" stop-color="#7c3aed"/></linearGradient>
                    </defs>
                    <rect x="30" y="12" width="160" height="36" rx="6" fill="url(#g1)" opacity="0.95" />
                    <text x="110" y="34" text-anchor="middle" fill="#fff" font-weight="700">Database (Sales & Products)</text>

                    <line x1="190" y1="30" x2="230" y2="30" stroke="#94a3b8" stroke-width="2" marker-end="url(#arrow)" />

                    <rect x="240" y="12" width="160" height="36" rx="6" fill="#10b981" opacity="0.95" />
                    <text x="320" y="34" text-anchor="middle" fill="#fff" font-weight="700">Metrics Engine</text>

                    <line x1="400" y1="30" x2="440" y2="30" stroke="#94a3b8" stroke-width="2" />
                    <rect x="450" y="12" width="160" height="36" rx="6" fill="#f59e0b" opacity="0.95" />
                    <text x="530" y="34" text-anchor="middle" fill="#fff" font-weight="700">BI Engine (Analysis & Forecast)</text>

                    <defs>
                        <marker id="arrow" markerWidth="10" markerHeight="10" refX="10" refY="5" orient="auto"><path d="M0,0 L10,5 L0,10 L3,5 z" fill="#94a3b8"/></marker>
                    </defs>
                </svg>
            </div>

        </div>

        <!-- Top Selling Products -->
        @if($topProducts->count() > 0)
        <div class="section">
            <div class="section-title">Top Selling Products</div>

            <table>
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>SKU</th>
                        <th class="text-right">Qty Sold</th>
                        <th class="text-right">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topProducts as $product)
                    <tr>
                        <td class="font-bold">{{ $product->name }}</td>
                        <td>{{ $product->sku }}</td>
                        <td class="text-right">{{ number_format($product->total_quantity) }}</td>
                        <td class="text-right font-bold">{{ $currency }} {{ number_format($product->total_revenue, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Payment Methods -->
        @if($paymentMethods->count() > 0)
        <div class="section">
            <div class="section-title">Payment Methods Breakdown</div>

            @foreach($paymentMethods as $method)
            <div class="payment-method">
                <div class="method-name">{{ ucfirst($method->payment_method) }}</div>
                <div class="method-stats">
                    {{ $method->count }} transactions • {{ $currency }} {{ number_format($method->total, 2) }}
                    ({{ number_format(($method->total / $summary['total_revenue']) * 100, 1) }}% of total)
                </div>
            </div>
            @endforeach
        </div>
        @endif

        <!-- Product Inventory -->
        <div class="section page-break">
            <div class="section-title">Complete Product Inventory</div>

            <table>
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th class="text-right">Stock</th>
                        <th class="text-right">Cost Price</th>
                        <th class="text-right">Selling Price</th>
                        <th class="text-right">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                    <tr>
                        <td class="font-bold">{{ $product->name }}</td>
                        <td>{{ $product->sku }}</td>
                        <td>{{ $product->category->name ?? '-' }}</td>
                        <td class="text-right">
                            @if($product->quantity <= 0)
                                <span class="badge badge-danger">{{ $product->quantity }}</span>
                            @elseif($product->quantity <= 10)
                                <span class="badge badge-warning">{{ $product->quantity }}</span>
                            @else
                                <span class="badge badge-success">{{ $product->quantity }}</span>
                            @endif
                        </td>
                        <td class="text-right">{{ $currency }} {{ number_format($product->cost_price, 2) }}</td>
                        <td class="text-right font-bold">{{ $currency }} {{ number_format($product->selling_price, 2) }}</td>
                        <td class="text-right">
                            @php
                                $margin = $product->cost_price > 0 ? (($product->selling_price - $product->cost_price) / $product->cost_price) * 100 : 0;
                            @endphp
                            {{ number_format($margin, 1) }}%
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Analysis & Insights -->
        <div class="section">
            <div class="section-title">AI Analysis & Insights</div>

            <div style="background: #edf2f7; padding: 15px; border-radius: 8px; margin-top: 10px;">
                <p style="margin-bottom: 10px;"><strong>Performance Analysis:</strong></p>
                <ul style="margin-left: 20px; line-height: 1.8;">
                    <li>Your business generated <strong>{{ $currency }} {{ number_format($summary['total_revenue'], 2) }}</strong> in revenue during this {{ strtolower($period) }}.</li>
                    <li>Gross profit of <strong>{{ $currency }} {{ number_format($summary['gross_profit'], 2) }}</strong> with a profit margin of <strong>{{ number_format($summary['profit_margin'], 1) }}%</strong>.</li>
                    <li>Completed <strong>{{ number_format($summary['total_orders']) }}</strong> orders with an average value of <strong>{{ $currency }} {{ number_format($summary['average_order_value'], 2) }}</strong>.</li>
                    <li>Current inventory consists of <strong>{{ number_format($products->count()) }}</strong> products across multiple categories.</li>
                    @if($topProducts->count() > 0)
                    <li>Top performing product: <strong>{{ $topProducts->first()->name }}</strong> with {{ number_format($topProducts->first()->total_quantity) }} units sold.</li>
                    @endif
                </ul>
            </div>
        </div>
    </div>

    <div class="footer">
        <div>Generated by AI Business Assistant • {{ $business->name }} • {{ $generatedAt }}</div>
        <div style="margin-top: 5px;">This is a computer-generated report and does not require a signature.</div>
    </div>
</body>
</html>

