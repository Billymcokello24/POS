<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Subscription;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class PdfService
{
    /**
     * Get company information from config/CMS
     */
    protected function getCompanyInfo(): array
    {
        return [
            'name' => config('app.company_name', 'Doitix Tech Labs'),
            'software_name' => config('app.name', 'ModernPOS'),
            'address' => config('app.company_address', 'Nairobi, Kenya'),
            'phone' => config('app.company_phone', '+254759814390'),
            'email' => config('app.company_email', 'info@doitrixtech.co.ke'),
            'website' => config('app.company_website', 'www.doitrixtech.co.ke'),
        ];
    }

    /**
     * Calculate VAT breakdown (16%)
     */
    protected function calculateVAT(float $totalAmount): array
    {
        $vatRate = 0.16; // 16% VAT
        $subtotal = $totalAmount / (1 + $vatRate);
        $vat = $totalAmount - $subtotal;

        return [
            'subtotal' => $subtotal,
            'vat' => $vat,
            'vat_rate' => $vatRate * 100,
            'total' => $totalAmount,
        ];
    }

    /**
     * Generate payment receipt PDF
     */
    public function generateReceipt(Subscription $subscription): \Barryvdh\DomPDF\PDF
    {
        $business = $subscription->business;
        $billingCycle = $subscription->payment_details['billing_cycle'] ?? 'monthly';
        $billingLabel = $billingCycle === 'yearly' ? 'Yearly' : 'Monthly';

        // Calculate VAT breakdown
        $vatBreakdown = $this->calculateVAT($subscription->amount);

        $data = [
            'company' => $this->getCompanyInfo(),
            'title' => 'Payment Receipt',
            'status' => 'PAID',
            'receipt_number' => 'RCP-' . str_pad($subscription->id, 6, '0', STR_PAD_LEFT),
            'date' => now()->format('F j, Y'),
            'business' => [
                'name' => $business->name,
                'address' => $business->address ?? 'N/A',
                'email' => $business->email ?? $business->user->email ?? 'N/A',
                'phone' => $business->phone ?? 'N/A',
            ],
            'subscription' => [
                'plan_name' => $subscription->plan_name,
                'billing_cycle' => $billingLabel,
                'amount' => number_format($subscription->amount, 2),
                'subtotal' => number_format($vatBreakdown['subtotal'], 2),
                'vat' => number_format($vatBreakdown['vat'], 2),
                'vat_rate' => $vatBreakdown['vat_rate'],
                'currency' => $subscription->currency ?? 'KES',
                'starts_at' => $subscription->starts_at ? $subscription->starts_at->format('F j, Y') : 'N/A',
                'ends_at' => $subscription->ends_at ? $subscription->ends_at->format('F j, Y') : 'N/A',
                'transaction_id' => $subscription->mpesa_receipt ?? $subscription->transaction_id ?? 'N/A',
                'payment_method' => $subscription->payment_method ?? 'M-PESA',
            ],
        ];

        return Pdf::loadView('pdf.receipt', $data)
            ->setPaper('a4')
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10);
    }

    /**
     * Generate renewal invoice PDF
     */
    public function generateInvoice(Subscription $subscription, int $daysUntilExpiry = 0): \Barryvdh\DomPDF\PDF
    {
        $business = $subscription->business;
        $billingCycle = $subscription->payment_details['billing_cycle'] ?? 'monthly';
        $billingLabel = $billingCycle === 'yearly' ? 'Yearly' : 'Monthly';

        // Calculate renewal period
        $renewalStartDate = $subscription->ends_at ?? now();
        $renewalEndDate = $billingCycle === 'yearly'
            ? $renewalStartDate->copy()->addYear()
            : $renewalStartDate->copy()->addMonth();

        // Expiry message
        $expiryMessage = match($daysUntilExpiry) {
            0 => 'today',
            1 => 'tomorrow',
            default => "in {$daysUntilExpiry} days"
        };

        // Calculate VAT breakdown
        $vatBreakdown = $this->calculateVAT($subscription->amount);

        $data = [
            'company' => $this->getCompanyInfo(),
            'title' => 'Subscription Renewal Invoice',
            'status' => 'DUE',
            'invoice_number' => 'INV-' . str_pad($subscription->id, 6, '0', STR_PAD_LEFT) . '-R',
            'date' => now()->format('F j, Y'),
            'due_date' => $subscription->ends_at ? $subscription->ends_at->format('F j, Y') : 'N/A',
            'expiry_message' => $expiryMessage,
            'business' => [
                'name' => $business->name,
                'address' => $business->address ?? 'N/A',
                'email' => $business->email ?? $business->user->email ?? 'N/A',
                'phone' => $business->phone ?? 'N/A',
            ],
            'subscription' => [
                'plan_name' => $subscription->plan_name,
                'billing_cycle' => $billingLabel,
                'amount' => number_format($subscription->amount, 2),
                'subtotal' => number_format($vatBreakdown['subtotal'], 2),
                'vat' => number_format($vatBreakdown['vat'], 2),
                'vat_rate' => $vatBreakdown['vat_rate'],
                'currency' => $subscription->currency ?? 'KES',
                'renewal_starts_at' => $renewalStartDate->format('F j, Y'),
                'renewal_ends_at' => $renewalEndDate->format('F j, Y'),
            ],
        ];

        return Pdf::loadView('pdf.invoice', $data)
            ->setPaper('a4')
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10);
    }

    /**
     * Generate plans brochure PDF
     */
    public function generatePlansBrochure(): \Barryvdh\DomPDF\PDF
    {
        $plans = \App\Models\Plan::with('features')
            ->where('is_active', true)
            ->orderBy('price_monthly', 'asc')
            ->get();

        $data = [
            'company' => $this->getCompanyInfo(),
            'plans' => $plans,
            'date' => now()->format('F j, Y'),
        ];

        return Pdf::loadView('pdf.plans', $data)
            ->setPaper('a4')
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10);
    }
}
