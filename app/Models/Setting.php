<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key-value settings storage for system configuration values
 * such as tax rate, currency symbol, and alert thresholds.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Retrieve a setting value by key with caching.
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
