<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingSetting extends Model
{
    protected $fillable = [
        'points_earn_amount',
        'points_earn_count',
        'points_redeem_value',
        'bag_fee',
    ];

    /**
     * Mirrors the migration's column defaults. Without these, a fresh
     * firstOrCreate([]) row would apply the DB defaults on save but the
     * in-memory model returned to the caller would still read those
     * attributes as null until re-fetched — silently breaking earnPercent()
     * the very first time the app ever needs this row.
     */
    protected $attributes = [
        'points_earn_amount' => 100,
        'points_earn_count' => 1,
        'points_redeem_value' => 1,
        'bag_fee' => 0,
    ];

    protected function casts(): array
    {
        return [
            'points_earn_amount' => 'decimal:2',
            'points_earn_count' => 'decimal:2',
            'points_redeem_value' => 'decimal:2',
            'bag_fee' => 'decimal:2',
        ];
    }

    /**
     * There is only ever one row. Get it, creating the default row the
     * first time anything asks for it.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /**
     * The admin edits "spend X, earn Y points" directly; the rest of the
     * codebase still works in percent-of-amount-paid terms internally.
     */
    public function earnPercent(): float
    {
        return $this->points_earn_amount > 0
            ? ((float) $this->points_earn_count / (float) $this->points_earn_amount) * 100
            : 0.0;
    }
}
