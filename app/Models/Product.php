<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'barcode',
        'cost_price',
        'selling_price',
        'stock_qty',
        'reorder_level',
        'expiry_date',
        'unit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'is_active' => 'boolean',
            'expiry_date' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isLowStock(): bool
    {
        return $this->stock_qty <= $this->reorder_level;
    }

    public function isNearExpiry(int $days = 7): bool
    {
        return $this->expiry_date !== null
            && ! $this->expiry_date->isPast()
            && $this->expiry_date->lte(now()->addDays($days));
    }
}
