<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SubscriptionPayment extends Model
{
    // default table name (used if schema checks are not available yet)
    protected $table = 'subscriptions_payments';

    // cache resolved table name to avoid repeated schema checks
    protected static ?string $resolvedTable = null;

    protected $fillable = [
        'subscription_id',
        'business_id',
        'checkout_request_id',
        'merchant_request_id',
        'mpesa_receipt',
        'phone',
        'amount',
        'status',
        'metadata',
        'raw_response',
        // Denormalized Snapshot Fields
        'business_name',
        'plan_id',
        'plan_name',
        'billing_cycle',
        'plan_start_date',
        'plan_end_date',
        'approval_status',
        'approved_at',
        'approved_by',
        'rejection_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'raw_response' => 'array',
    ];

    public function getTable()
    {
        if (self::$resolvedTable) return self::$resolvedTable;

        try {
            if (Schema::hasTable('subscriptions_payments')) {
                self::$resolvedTable = 'subscriptions_payments';
            } elseif (Schema::hasTable('subscription_payments')) {
                self::$resolvedTable = 'subscription_payments';
            } else {
                // default to preferred name
                self::$resolvedTable = $this->table ?? 'subscriptions_payments';
            }
        } catch (\Throwable $_) {
            // If schema not available (during certain bootstrap phases), fall back to configured table
            self::$resolvedTable = $this->table ?? 'subscriptions_payments';
        }

        return self::$resolvedTable;
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    protected static function booted()
    {
        // SubscriptionPayment is a secondary ledger. 
        // Activation is driven by MpesaPayment (Financial Truth)
        static::saved(function (SubscriptionPayment $sp) {
            Log::info('SubscriptionPayment secondary ledger updated', [
                'id' => $sp->id,
                'status' => $sp->status,
                'subscription_id' => $sp->subscription_id
            ]);
        });

        static::created(function (SubscriptionPayment $sp) {
            try {
                $table = (self::$resolvedTable) ?? ($sp->getTable() ?? 'subscriptions_payments');
                \Illuminate\Support\Facades\Log::info('SubscriptionPayment record created', ['id' => $sp->id, 'table' => $table, 'subscription_id' => $sp->subscription_id]);
            } catch (\Throwable $_) { }
        });
    }
}
