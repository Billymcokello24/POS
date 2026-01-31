<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class MpesaPayment extends Model
{
    use HasFactory;

    protected $table = 'mpesa_payments';
    
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';

    protected $casts = [
        'metadata' => 'array',
        'raw_response' => 'array',
    ];

    protected $fillable = [
        'business_id',
        'subscription_id', // SaaS Link
        'sale_id',
        'checkout_request_id',
        'merchant_request_id',
        'receipt',
        'phone',
        'amount',
        'reference',
        'account_reference',
        'status',
        'result_code', // SaaS Truth
        'metadata',
        'raw_response',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public static function resolveStatusFromCode($code): string
    {
        if ($code === null) return self::STATUS_PENDING;
        // Blueprint Point 1 & 9: Zero is success, non-zero is failed
        return (int)$code === 0 ? self::STATUS_SUCCESS : self::STATUS_FAILED;
    }

    protected static function booted()
    {
        static::saving(function (MpesaPayment $mp) {
            // Re-resolve status if result_code is set or changed (Financial Truth)
            if ($mp->result_code !== null) {
                $mp->status = self::resolveStatusFromCode($mp->result_code);
            }
        });

        static::saved(function (MpesaPayment $mp) {
            // Activation Logic (Point 6: Automate subscription handling) 
            // Only trigger on definitive success (result_code === 0)
            if ($mp->isDirty('result_code') && (int)$mp->result_code === 0) {
                try {
                    $service = app(\App\Services\SubscriptionActivationService::class);
                    $service->finalizeFromPayment([
                        'checkout_request_id' => $mp->checkout_request_id,
                        'mpesa_receipt' => $mp->receipt,
                        'result_code' => $mp->result_code,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('MpesaPayment Observer: Activation failed', [
                        'error' => $e->getMessage(),
                        'mpesa_payment_id' => $mp->id
                    ]);
                }
            }
        });
    }
}
