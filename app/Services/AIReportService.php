<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI-Powered Business Intelligence Service
 *
 * RESPONSIBILITY:
 * - Analyzes and interprets database-derived metrics
 * - Provides narrative insights and recommendations
 * - Does NOT generate or modify raw financial data
 *
 * ARCHITECTURE:
 * Database → ReportService (computes metrics) → AIReportService (analyzes) → Export
 */
class AIReportService
{
    protected $apiKey;
    protected $baseUri;
    protected $model;
    protected $timeout;
    protected $driver;

    public function __construct()
    {
        $this->driver = config('ai.driver', 'openai');
        $this->apiKey = config('ai.api_key');
        $this->baseUri = config('ai.base_uri', 'https://openrouter.ai/api');
        $this->model = config('ai.model', 'openai/gpt-4');
        $this->timeout = config('ai.timeout_seconds', 120);
    }

    /**
     * Analyze business report data with AI
     *
     * @param array $reportData - Pre-computed metrics from database
     * @return array - AI-generated insights and recommendations
     */
    public function analyzeReport(array $reportData): array
    {
        Log::info('AI Business Intelligence: Starting analysis', [
            'driver' => $this->driver,
            'model' => $this->model,
        ]);

        // Use fallback if AI is disabled or API key missing
        if ($this->driver === 'mock' || empty($this->apiKey)) {
            Log::info('AI: Using rule-based fallback analysis');
            return $this->fallbackAnalysis($reportData);
        }

        // Attempt AI-powered analysis
        try {
            return $this->aiPoweredAnalysis($reportData);
        } catch (\Exception $e) {
            Log::error('AI Analysis Failed - Using Fallback', [
                'error' => $e->getMessage(),
            ]);
            return $this->fallbackAnalysis($reportData);
        }
    }

    /**
     * AI-Powered Analysis using OpenAI/OpenRouter
     */
    protected function aiPoweredAnalysis(array $reportData): array
    {
        $systemPrompt = $this->getSystemPrompt();
        $userPrompt = $this->generateDataContract($reportData);

        // Prepare headers
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];

        // Add OpenRouter specific headers
        if (strpos($this->baseUri, 'openrouter.ai') !== false) {
            $headers['HTTP-Referer'] = config('app.url');
            $headers['X-Title'] = 'POS Business Intelligence Engine';
        }

        Log::info('AI: Making API request', [
            'endpoint' => $this->baseUri,
            'model' => $this->model,
        ]);

        $response = Http::timeout($this->timeout)
            ->connectTimeout(30)
            ->retry(2, 1000)
            ->withHeaders($headers)
            ->post($this->baseUri . '/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 2500,
            ]);

        if ($response->successful()) {
            $result = $response->json();
            $content = $result['choices'][0]['message']['content'] ?? '';

            Log::info('AI: Analysis completed successfully');
            return $this->parseAIResponse($content, $reportData);
        }

        // Log failure and use fallback
        Log::error('AI API Request Failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new \Exception('AI API request failed: ' . $response->status());
    }

    /**
     * System Prompt: Defines AI's role and constraints
     */
    protected function getSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an AI-Powered Business Intelligence Analyst operating inside a production financial system.

CRITICAL RULES:
✓ Database data is the single source of truth
✓ All financial metrics are pre-computed deterministically
✓ Your role is to INTERPRET, not generate data
✓ Be accurate, explainable, and audit-ready

YOUR RESPONSIBILITIES:
1. Analyze structured business metrics
2. Identify trends, patterns, anomalies
3. Detect risks and opportunities
4. Provide executive-level insights
5. Suggest actionable recommendations

DO NOT:
✗ Modify or calculate raw financial numbers
✗ Invent data or make assumptions
✗ Return HTML or UI markup

OUTPUT FORMAT (JSON):
{
  "executive_summary": "2-3 paragraph business overview in plain language",
  "insights": [
    {
      "category": "revenue|profit|sales|products|trends",
      "title": "Brief insight title",
      "description": "Detailed explanation of what the data means",
      "severity": "positive|neutral|negative"
    }
  ],
  "recommendations": [
    {
      "priority": "high|medium|low",
      "title": "Action item title",
      "description": "What to do and why",
      "expected_impact": "Quantified or qualitative outcome"
    }
  ]
}

Write professionally. Be specific. Reference actual metrics. Explain WHY things matter.
PROMPT;
    }

    /**
     * Data Contract: Send clean, structured metrics to AI
     * This is what keeps AI honest and auditable
     */
    protected function generateDataContract(array $reportData): string
    {
        $contract = [
            'business' => [
                'name' => $reportData['business']['name'],
                'type' => $reportData['business']['business_type'] ?? 'retail',
                'currency' => $reportData['business']['currency'],
            ],
            'reporting_period' => [
                'period' => $reportData['reporting_period']['period'],
                'start_date' => $reportData['reporting_period']['start_date'],
                'end_date' => $reportData['reporting_period']['end_date'],
            ],
            'kpis' => [
                'revenue' => [
                    'current' => $reportData['profit_loss']['revenue'],
                    'change_percent' => $reportData['kpis']['revenue']['change_percent'],
                    'trend' => $reportData['kpis']['revenue']['trend'],
                ],
                'transactions' => [
                    'current' => $reportData['sales_summary']['total_transactions'],
                    'change_percent' => $reportData['kpis']['transactions']['change_percent'],
                    'trend' => $reportData['kpis']['transactions']['trend'],
                ],
                'average_order_value' => [
                    'current' => $reportData['sales_summary']['average_transaction_value'],
                    'change_percent' => $reportData['kpis']['average_order_value']['change_percent'],
                    'trend' => $reportData['kpis']['average_order_value']['trend'],
                ],
                'profit_margin' => [
                    'current' => $reportData['profit_loss']['net_margin_percent'],
                    'change_percent' => $reportData['kpis']['profit_margin']['change_percent'],
                    'trend' => $reportData['kpis']['profit_margin']['trend'],
                ],
            ],
            'financial_summary' => [
                'revenue' => $reportData['profit_loss']['revenue'],
                'cogs' => $reportData['profit_loss']['cogs'],
                'gross_profit' => $reportData['profit_loss']['gross_profit'],
                'gross_margin_percent' => $reportData['profit_loss']['gross_margin_percent'],
                'operating_expenses' => $reportData['profit_loss']['operating_expenses'],
                'net_profit' => $reportData['profit_loss']['net_profit'],
                'net_margin_percent' => $reportData['profit_loss']['net_margin_percent'],
            ],
            'product_performance' => array_slice($reportData['top_products'] ?? [], 0, 10),
            'underperforming_products' => $reportData['underperforming_products'] ?? [],
        ];

        $prompt = "Analyze this business performance data and generate AI-powered insights:\n\n";
        $prompt .= "```json\n" . json_encode($contract, JSON_PRETTY_PRINT) . "\n```\n\n";
        $prompt .= "Provide executive summary, key insights, and actionable recommendations.\n";
        $prompt .= "Focus on what the numbers MEAN for the business, not just what they are.\n";
        $prompt .= "Identify risks, opportunities, and specific actions to take.";

        return $prompt;
    }

    /**
     * Parse AI response into structured format
     */
    protected function parseAIResponse(string $content, array $reportData): array
    {
        // Try to extract JSON from response
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if ($decoded && isset($decoded['executive_summary'])) {
                return [
                    'executive_summary' => $decoded['executive_summary'],
                    'insights' => $decoded['insights'] ?? [],
                    'recommendations' => $decoded['recommendations'] ?? [],
                ];
            }
        }

        // Fallback: treat as plain text summary
        return [
            'executive_summary' => $content,
            'insights' => [],
            'recommendations' => $this->generateBasicRecommendations($reportData),
        ];
    }

    /**
     * Fallback Analysis: Rule-based when AI unavailable
     * Still data-driven, just less sophisticated
     */
    protected function fallbackAnalysis(array $reportData): array
    {
        $insights = [];
        $recommendations = [];

        // Extract metrics
        $revenueChange = $reportData['kpis']['revenue']['change_percent'];
        $netMargin = $reportData['profit_loss']['net_margin_percent'];
        $transactionChange = $reportData['kpis']['transactions']['change_percent'];
        $aovChange = $reportData['kpis']['average_order_value']['change_percent'];

        // Revenue Analysis
        if ($revenueChange > 10) {
            $insights[] = [
                'category' => 'revenue',
                'title' => 'Strong Revenue Growth',
                'description' => sprintf(
                    'Revenue increased by %.1f%% compared to previous period, indicating positive momentum and strong market demand.',
                    abs($revenueChange)
                ),
                'severity' => 'positive',
            ];
        } elseif ($revenueChange < -5) {
            $insights[] = [
                'category' => 'revenue',
                'title' => 'Revenue Decline Alert',
                'description' => sprintf(
                    'Revenue decreased by %.1f%%. Immediate investigation needed to identify root causes and reverse the trend.',
                    abs($revenueChange)
                ),
                'severity' => 'negative',
            ];
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'Address Revenue Decline',
                'description' => 'Conduct customer feedback analysis, review pricing strategy, analyze competitor activity, and launch retention campaigns.',
                'expected_impact' => 'Return to growth trajectory within 1-2 months',
            ];
        }

        // Profitability Analysis
        if ($netMargin < 10) {
            $insights[] = [
                'category' => 'profit',
                'title' => 'Low Profit Margins Detected',
                'description' => sprintf(
                    'Net margin at %.1f%% is below healthy retail threshold of 10%%, limiting reinvestment capacity and growth potential.',
                    $netMargin
                ),
                'severity' => 'negative',
            ];
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'Improve Profitability',
                'description' => 'Optimize product mix, negotiate supplier costs, review pricing, reduce waste, and focus on high-margin items.',
                'expected_impact' => 'Increase net margin by 3-5% within one quarter',
            ];
        } elseif ($netMargin > 20) {
            $insights[] = [
                'category' => 'profit',
                'title' => 'Excellent Profitability',
                'description' => sprintf(
                    'Net margin of %.1f%% demonstrates strong operational efficiency and pricing power, well above industry standards.',
                    $netMargin
                ),
                'severity' => 'positive',
            ];
        }

        // Transaction Trends
        if ($transactionChange < -10) {
            $insights[] = [
                'category' => 'sales',
                'title' => 'Customer Traffic Declining',
                'description' => sprintf(
                    'Transaction volume down %.1f%%, indicating reduced foot traffic. Customer acquisition and retention require focus.',
                    abs($transactionChange)
                ),
                'severity' => 'negative',
            ];
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'Boost Customer Acquisition',
                'description' => 'Launch loyalty program, increase marketing spend, improve in-store experience, and offer targeted promotions.',
                'expected_impact' => 'Increase customer visits by 15-20% over next quarter',
            ];
        }

        // AOV Analysis
        if ($aovChange < -10) {
            $recommendations[] = [
                'priority' => 'medium',
                'title' => 'Increase Average Order Value',
                'description' => 'Train staff on upselling, create product bundles, implement tiered pricing, and suggest add-ons at checkout.',
                'expected_impact' => 'Increase AOV by 12-18%',
            ];
        }

        // Product Portfolio
        $underperforming = count($reportData['underperforming_products'] ?? []);
        if ($underperforming > 5) {
            $recommendations[] = [
                'priority' => 'medium',
                'title' => 'Optimize Product Portfolio',
                'description' => 'Review slow-moving items, consider discounting or discontinuing, and replace with proven performers.',
                'expected_impact' => 'Reduce inventory costs 10-15% and improve turnover',
            ];
        }

        // Always add monitoring
        $recommendations[] = [
            'priority' => 'low',
            'title' => 'Continuous Monitoring',
            'description' => 'Generate weekly intelligence reports to track KPIs and identify trends before they become problems.',
            'expected_impact' => 'Faster response time and data-driven decision making',
        ];

        return [
            'executive_summary' => $this->generateExecutiveSummary($reportData, $insights),
            'insights' => $insights,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Generate executive summary
     */
    protected function generateExecutiveSummary(array $reportData, array $insights): string
    {
        $business = $reportData['business']['name'];
        $period = $reportData['reporting_period']['period'];
        $currency = $reportData['business']['currency'];
        $revenue = number_format($reportData['profit_loss']['revenue'], 2);
        $netProfit = number_format($reportData['profit_loss']['net_profit'], 2);
        $netMargin = number_format($reportData['profit_loss']['net_margin_percent'], 1);
        $transactions = $reportData['sales_summary']['total_transactions'];
        $revenueChange = $reportData['kpis']['revenue']['change_percent'];

        $trend = $revenueChange > 0 ? 'growth' : 'decline';
        $profitability = $reportData['profit_loss']['net_margin_percent'] > 15 ? 'strong' : 'moderate';

        $positive = count(array_filter($insights, fn($i) => $i['severity'] === 'positive'));
        $negative = count(array_filter($insights, fn($i) => $i['severity'] === 'negative'));
        $health = $positive > $negative ? 'healthy' : ($negative > $positive ? 'requires attention' : 'stable');

        return sprintf(
            "%s generated %s %s in revenue for the %s period, showing %s of %.1f%% versus the previous period. " .
            "The business completed %d transactions with net profit of %s %s (%.1f%% margin), indicating %s profitability. " .
            "Overall performance is %s with %d positive indicators and %d areas needing attention. " .
            "%s",
            $business,
            $currency,
            $revenue,
            $period,
            $trend,
            abs($revenueChange),
            $transactions,
            $currency,
            $netProfit,
            $netMargin,
            $profitability,
            $health,
            $positive,
            $negative,
            $negative > 0
                ? "Immediate focus should be on addressing identified challenges to sustain growth momentum."
                : "Continue current strategies while monitoring market conditions and customer behavior."
        );
    }

    /**
     * Generate basic recommendations from data
     */
    protected function generateBasicRecommendations(array $reportData): array
    {
        return [
            [
                'priority' => 'low',
                'title' => 'Monitor Key Metrics',
                'description' => 'Continue tracking performance indicators and generate regular reports for data-driven decisions.',
                'expected_impact' => 'Better visibility and faster response to market changes',
            ],
        ];
    }
}
