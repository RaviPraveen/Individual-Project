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
