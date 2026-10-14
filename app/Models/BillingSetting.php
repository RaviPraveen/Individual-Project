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

    // Match the database defaults before a newly created model is reloaded.
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

    // Create the settings row on first use.
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function earnPercent(): float
    {
        return (float) $this->points_earn_percent;
    }
}
