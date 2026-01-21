<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Business extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'business_type',
        'address',
        'phone',
        'email',
        'tax_id',
        'logo',
        'receipt_prefix',
        'currency',
        'timezone',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function taxConfigurations(): HasMany
    {
        return $this->hasMany(TaxConfiguration::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function generateSaleNumber(): string
    {
        $prefix = $this->receipt_prefix ?? 'POS';
        $lastSale = $this->sales()->latest('id')->first();
        $nextNumber = $lastSale ? ((int) substr($lastSale->sale_number, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}

