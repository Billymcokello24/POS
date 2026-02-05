<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Business;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class InvoiceService
{
    /**
     * Generate a payment receipt PDF for successful subscription payment
     */
    public function generateReceipt(Subscription $subscription): string
    {
        $business = $subscription->business;
        $plan = $subscription->plan;

        $billingCycle = $subscription->payment_details['billing_cycle'] ?? 'monthly';
        $billingLabel = $billingCycle === 'yearly' ? 'Annual' : 'Monthly';

        $data = [
            'type' => 'receipt',
            'title' => 'PAYMENT RECEIPT',
            'status' => 'PAID',
            'receipt_number' => 'RCP-' . str_pad($subscription->id, 6, '0', STR_PAD_LEFT),
            'date' => now()->format('F d, Y'),
            'business' => [
                'name' => $business->name,
                'email' => $business->email,
                'phone' => $business->phone,
                'address' => $business->address ?? 'N/A',
            ],
            'subscription' => [
                'id' => $subscription->id,
                'plan_name' => $subscription->plan_name,
                'billing_cycle' => $billingLabel,
                'amount' => number_format($subscription->amount, 2),
                'currency' => $subscription->currency,
                'starts_at' => $subscription->starts_at->format('F d, Y'),
                'ends_at' => $subscription->ends_at->format('F d, Y'),
                'payment_method' => $subscription->payment_method,
                'transaction_id' => $subscription->mpesa_receipt ?? $subscription->transaction_id,
            ],
            'company' => [
                'name' => config('app.name', 'POS System'),
                'address' => 'P.O. Box 12345-00100, Nairobi, Kenya',
                'phone' => '+254 700 000000',
                'email' => 'billing@possystem.com',
            ],
        ];

        $pdf = Pdf::loadView('pdf.receipt', $data)
            ->setPaper('a4')
            ->setOption('margin-top', 0)
            ->setOption('margin-bottom', 0);

        // Save to storage
        $filename = 'receipt-' . $subscription->id . '-' . time() . '.pdf';
        $path = storage_path('app/public/receipts/' . $filename);

        // Create directory if not exists
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $pdf->save($path);

        return $path;
    }

    /**
     * Generate an invoice PDF for upcoming subscription renewal
     */
    public function generateInvoice(Subscription $subscription, int $daysUntilExpiry): string
    {
        $business = $subscription->business;
        $plan = $subscription->plan;

        $billingCycle = $subscription->payment_details['billing_cycle'] ?? 'monthly';
        $billingLabel = $billingCycle === 'yearly' ? 'Annual' : 'Monthly';

        // Calculate renewal amount
        $renewalAmount = $billingCycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;

        $data = [
            'type' => 'invoice',
            'title' => 'RENEWAL INVOICE',
            'status' => 'DUE',
            'invoice_number' => 'INV-' . str_pad($subscription->id, 6, '0', STR_PAD_LEFT) . '-R',
            'date' => now()->format('F d, Y'),
            'due_date' => $subscription->ends_at->format('F d, Y'),
            'days_until_due' => $daysUntilExpiry,
            'business' => [
                'name' => $business->name,
                'email' => $business->email,
                'phone' => $business->phone,
                'address' => $business->address ?? 'N/A',
            ],
            'subscription' => [
                'id' => $subscription->id,
                'plan_name' => $subscription->plan_name,
                'billing_cycle' => $billingLabel,
                'amount' => number_format($renewalAmount, 2),
                'currency' => $subscription->currency,
                'current_period_end' => $subscription->ends_at->format('F d, Y'),
                'next_period_start' => $subscription->ends_at->copy()->addDay()->format('F d, Y'),
                'next_period_end' => $billingCycle === 'yearly'
                    ? $subscription->ends_at->copy()->addYear()->format('F d, Y')
                    : $subscription->ends_at->copy()->addMonth()->format('F d, Y'),
            ],
            'company' => [
                'name' => config('app.name', 'POS System'),
                'address' => 'P.O. Box 12345-00100, Nairobi, Kenya',
                'phone' => '+254 700 000000',
                'email' => 'billing@possystem.com',
            ],
        ];

        $pdf = Pdf::loadView('pdf.invoice', $data)->setPaper('a4');

        // Save to storage
        $filename = 'invoice-' . $subscription->id . '-' . time() . '.pdf';
        $path = storage_path('app/public/invoices/' . $filename);

        // Create directory if not exists
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $pdf->save($path);

        return $path;
    }

    /**
     * Generate a plans brochure PDF for new registrations
     */
    public function generatePlansBrochure(): string
    {
        $plans = \App\Models\Plan::where('is_active', true)
            ->with('features')
            ->orderBy('price_monthly')
            ->get();

        $data = [
            'title' => 'SUBSCRIPTION PLANS',
            'date' => now()->format('F d, Y'),
            'plans' => $plans,
            'company' => [
                'name' => config('app.name', 'POS System'),
                'address' => 'P.O. Box 12345-00100, Nairobi, Kenya',
                'phone' => '+254 700 000000',
                'email' => 'sales@possystem.com',
                'website' => config('app.url'),
            ],
        ];

        $pdf = Pdf::loadView('pdf.plans', $data)->setPaper('a4');

        // Save to storage
        $filename = 'plans-brochure-' . time() . '.pdf';
        $path = storage_path('app/public/brochures/' . $filename);

        // Create directory if not exists
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $pdf->save($path);

        return $path;
    }
}
