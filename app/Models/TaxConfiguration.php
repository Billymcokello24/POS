<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxConfiguration extends Model
{
    protected $fillable = [
        'business_id',
        'name',
        'rate',
        'is_inclusive',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_inclusive' => 'boolean',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function calculateTax(float $amount): float
    {
        if ($this->is_inclusive) {
            return $amount - ($amount / (1 + ($this->rate / 100)));
        }
        return $amount * ($this->rate / 100);
    }
}
