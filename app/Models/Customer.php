<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use SoftDeletes, BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'name',
        'email',
        'phone',
        'address',
        'total_spent',
        'total_visits',
        'last_visit_at',
        'notes',
    ];

    protected $casts = [
        'total_spent' => 'decimal:2',
        'total_visits' => 'integer',
        'last_visit_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
