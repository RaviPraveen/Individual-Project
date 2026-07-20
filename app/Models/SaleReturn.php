<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    protected $fillable = [
        'return_no',
        'sale_id',
        'processed_by',
        'reason',
        'refund_method',
        'subtotal_refunded',
        'discount_refunded',
        'tax_refunded',
        'total_refunded',
        'points_clawed_back',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_refunded' => 'decimal:2',
            'discount_refunded' => 'decimal:2',
            'tax_refunded' => 'decimal:2',
            'total_refunded' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
