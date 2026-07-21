<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierReturn extends Model
{
    protected $fillable = [
        'supplier_id',
        'created_by',
        'return_date',
        'status',
        'reason_summary',
        'credit_note_value',
        'resolution',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'credit_note_value' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierReturnItem::class);
    }
}
