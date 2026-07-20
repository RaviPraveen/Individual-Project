<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingSetting extends Model
{
    protected $fillable = [
        'points_earn_percent',
        'points_redeem_value',
        'bag_fee',
    ];

    /**
     * Mirrors the migration's column defaults. Without these, a fresh
     * firstOrCreate([]) row would apply the DB defaults on save but the
     * in-memory model returned to the caller would still read those
     * attributes as null until re-fetched.
     */
    protected $attributes = [
        'points_earn_percent' => 1,
        'points_redeem_value' => 1,
        'bag_fee' => 0,
    ];

    protected function casts(): array
    {
        return [
            'points_earn_percent' => 'decimal:3',
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

    public function earnPercent(): float
    {
        return (float) $this->points_earn_percent;
    }
}
