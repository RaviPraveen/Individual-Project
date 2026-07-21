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
     * Cached with a sentinel for "key not found" — Cache::rememberForever
     * can't distinguish a genuinely missing key from one whose value is an
     * empty string, so a literal miss is represented by a sentinel object
     * rather than null. Same lesson as BillingSetting/ReceiptSetting's
     * firstOrCreate([]) default-attribute bug found earlier in this project.
     */
    public static function get(string $key, $default = null)
    {
        $missing = new \stdClass();
        $cached = Cache::rememberForever("setting:{$key}", function () use ($key, $missing) {
            $row = static::where('key', $key)->first();

            return $row ? $row->value : $missing;
        });

        return $cached instanceof \stdClass ? $default : $cached;
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
