<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

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

    public static function allPairs(): array
    {
        return static::query()->pluck('value', 'key')->all();
    }
}
