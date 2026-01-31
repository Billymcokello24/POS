<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Subscription extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'business_id',
        'plan_id',
        'plan_name',
        'amount',
        'currency',
        'status',
        'transaction_id',
        'payment_method',
        'payment_details',
        'starts_at',
        'ends_at',
        'mpesa_receipt',
        'mpesa_stk',
        'verified_at',
        'verified_by',
        'activated_at',
        'payment_confirmed_at',
        'checkout_request_id',
        'is_active', // SaaS
        'is_verified', // SaaS
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'verified_at' => 'datetime',
        'activated_at' => 'datetime',
        'payment_confirmed_at' => 'datetime',
        'amount' => 'decimal:2',
        'payment_details' => 'array',
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive()
    {
        return $this->status === 'active' && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /**
     * REMOVED: Observer that created SubscriptionPayment records for pending subscriptions.
     * 
     * This violated the payment-as-truth principle.
     * Subscriptions should ONLY exist AFTER payment confirmation.
     * 
     * The correct flow is:
     * 1. User initiates payment → MpesaPayment record created
     * 2. Callback received → MpesaPayment updated
     * 3. If successful → Subscription created by SubscriptionActivationService
     * 4. SubscriptionPayment created as ledger entry
     */
}
