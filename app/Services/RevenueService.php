<?php

namespace App\Services;

use App\Models\MpesaPayment;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class RevenueService
{
    /**
     * Get Total Revenue from successful M-Pesa payments (The Truth)
     */
    public function getTotalRevenue(): float
    {
        return (float) MpesaPayment::whereIn('status', [MpesaPayment::STATUS_SUCCESS, 'completed'])
            ->where('result_code', 0)
            ->sum('amount');
    }

    public function getRevenueToday(): float
    {
        return $this->getRevenueByPeriod(now()->startOfDay(), now()->endOfDay());
    }

    public function getRevenueThisMonth(): float
    {
        return $this->getRevenueByPeriod(now()->startOfMonth(), now()->endOfMonth());
    }

    public function getRevenueThisYear(): float
    {
        return $this->getRevenueByPeriod(now()->startOfYear(), now()->endOfYear());
    }

    /**
     * Get Revenue for a specific period
     */
    public function getRevenueByPeriod($startDate, $endDate): float
    {
        return (float) MpesaPayment::whereIn('status', [MpesaPayment::STATUS_SUCCESS, 'completed'])
            ->where('result_code', 0)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');
    }

    /**
     * Calculate MRR (Monthly Recurring Revenue)
     * Sum of monthly subscription amounts + (yearly amounts / 12)
     */
    public function getMRR(): float
    {
        // MRR should reflect current active plan values for companies with active/verified status
        $activeSubscriptions = Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->where('is_active', true)
            ->where('is_verified', true)
            ->where(function($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->get();

        $mrr = 0;
        foreach ($activeSubscriptions as $sub) {
            $billingCycle = $sub->payment_details['billing_cycle'] ?? 'monthly';
            $amount = (float) $sub->amount;

            if ($billingCycle === 'yearly') {
                $mrr += ($amount / 12);
            } else {
                $mrr += $amount;
            }
        }

        return round($mrr, 2);
    }

    /**
     * Calculate ARR (Annual Recurring Revenue)
     */
    public function getARR(): float
    {
        return round($this->getMRR() * 12, 2);
    }

    /**
     * Get Conversion Metrics
     * Ratio of successful payments to total initiated payments (last 30 days)
     */
    public function getConversionMetrics(): array
    {
        $since = now()->subDays(30);
        
        $total = MpesaPayment::where('created_at', '>=', $since)->count();
        $successful = MpesaPayment::where('created_at', '>=', $since)
            ->whereIn('status', [MpesaPayment::STATUS_SUCCESS, 'completed'])
            ->where('result_code', 0)
            ->count();
            
        return [
            'total_attempts' => $total,
            'successful_payments' => $successful,
            'conversion_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0
        ];
    }
}
