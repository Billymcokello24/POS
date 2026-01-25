<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    protected $table = 'subscription_payments';

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
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'raw_response' => 'array',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
