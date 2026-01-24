<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpesaPayment extends Model
{
    use HasFactory;

    protected $table = 'mpesa_payments';

    protected $casts = [
        'metadata' => 'array',
        'raw_response' => 'array',
    ];

    protected $fillable = [
        'business_id',
        'sale_id',
        'checkout_request_id',
        'merchant_request_id',
        'receipt',
        'phone',
        'amount',
        'reference',
        'account_reference',
        'status',
        'metadata',
        'raw_response',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
