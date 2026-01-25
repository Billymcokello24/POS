<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use SoftDeletes, BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'sale_number',
        'cashier_id',
        'customer_id',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'status',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    protected $appends = ['total_paid', 'change_amount'];


    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // Relation to owning business (needed for sale number generation)
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getTotalPaidAttribute(): float
    {
        return $this->payments()
            ->where('status', 'completed')
            ->sum('amount');
    }

    public function getChangeAmountAttribute(): float
    {
        $totalPaid = $this->total_paid;
        return max(0, $totalPaid - $this->total);
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($sale) {
            if (!$sale->sale_number) {
                // Prefer explicit relationship if set
                $business = null;

                // 1) Prefer explicit business_id attribute (most reliable during create)
                if (!empty($sale->business_id)) {
                    try {
                        $business = \App\Models\Business::find($sale->business_id);
                    } catch (\Throwable $e) {
                        $business = null;
                    }
                }

                // 2) If still null, try the relation (may not be set for new models)
                if (! $business) {
                    try {
                        $business = $sale->business;
                    } catch (\Throwable $e) {
                        $business = null;
                    }
                }

                // Fall back to authenticated user's current business
                if (! $business && auth()->check()) {
                    $business = \App\Models\Business::find(auth()->user()->current_business_id);
                }

                if ($business) {
                    $sale->sale_number = $business->generateSaleNumber();
                } else {
                    // Ultimate fallback to a unique identifier to avoid error
                    $sale->sale_number = 'POS-' . strtoupper(uniqid());
                }
            }
        });
    }
}
