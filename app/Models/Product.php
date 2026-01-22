<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'category_id',
        'sku',
        'barcode',
        'barcode_type',
        'name',
        'description',
        'cost_price',
        'selling_price',
        'quantity',
        'reorder_level',
        'unit',
        'image',
        'track_inventory',
        'is_active',
        'tax_configuration_id',
        'variants',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'quantity' => 'integer',
        'reorder_level' => 'integer',
        'track_inventory' => 'boolean',
        'is_active' => 'boolean',
        'variants' => 'array',
    ];

    protected $appends = ['profit_margin', 'is_low_stock'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function taxConfiguration(): BelongsTo
    {
        return $this->belongsTo(TaxConfiguration::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function getProfitMarginAttribute(): float
    {
        if ($this->cost_price == 0) {
            return 0;
        }
        return (($this->selling_price - $this->cost_price) / $this->cost_price) * 100;
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->track_inventory && $this->quantity <= $this->reorder_level;
    }

    public function decreaseStock(int $quantity, string $type = 'SALE', $reference = null, int $userId = null): void
    {
        if (!$this->track_inventory) {
            return;
        }

        $quantityBefore = $this->quantity;
        $this->quantity -= $quantity;
        $this->save();

        $this->inventoryTransactions()->create([
            'business_id' => $this->business_id,
            'type' => $type,
            'quantity' => -$quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $this->quantity,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id,
            'created_by' => $userId ?? auth()->id(),
        ]);
    }

    public function increaseStock(int $quantity, string $type = 'IN', $reference = null, int $userId = null): void
    {
        if (!$this->track_inventory) {
            return;
        }

        $quantityBefore = $this->quantity;
        $this->quantity += $quantity;
        $this->save();

        $this->inventoryTransactions()->create([
            'business_id' => $this->business_id,
            'type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $this->quantity,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id,
            'created_by' => $userId ?? auth()->id(),
        ]);
    }
}
