<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Plain key-value store for admin-editable business settings that don't
 * belong to a more specific settings model (ReceiptSetting/BillingSetting
 * already own shop branding and loyalty rates — see memory on why this
 * table only holds the gap keys: tax_rate, currency_symbol,
 * low_stock_threshold_default).
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Cached with a "found" flag alongside the value so a literal miss can
     * be told apart from a genuinely empty string. The flag lives in an
     * array rather than an object sentinel — the database cache driver
     * unserializes with allowed_classes disabled, so any cached object
     * (even a bare stdClass) comes back as __PHP_Incomplete_Class. Arrays
     * don't hit that restriction. Same underlying lesson as
     * BillingSetting/ReceiptSetting's firstOrCreate([]) default-attribute
     * bug found earlier in this project: know what "missing" looks like.
     */
    public static function get(string $key, $default = null)
    {
        $cached = Cache::rememberForever("setting:{$key}", function () use ($key) {
            $row = static::where('key', $key)->first();

            return ['found' => (bool) $row, 'value' => $row?->value];
        });

        return $cached['found'] ? $cached['value'] : $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting:{$key}");
    }

    /**
     * All key => value pairs currently stored. Named allPairs() rather than
     * overriding Eloquent's own all() (which returns a Collection of model
     * instances) — shadowing it would silently break anyone expecting the
     * standard Eloquent behavior.
     */
    public static function allPairs(): array
    {
        return static::query()->pluck('value', 'key')->all();
    }
}
