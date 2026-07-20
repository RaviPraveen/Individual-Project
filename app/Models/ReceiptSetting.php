<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiptSetting extends Model
{
    protected $fillable = [
        'shop_name',
        'branch_name',
        'address',
        'phone',
        'email',
        'website',
        'tax_number',
        'business_reg_number',
        'footer_message',
        'thank_you_message',
        'return_policy',
        'paper_size',
        'receipt_width',
        'header_alignment',
        'footer_alignment',
        'receipt_margin',
        'receipt_padding',
        'font_family',
        'font_size',
        'font_weight',
        'logo_path',
    ];

    /**
     * Mirrors the migration's column defaults. Without these, a fresh
     * firstOrCreate([]) row would apply the DB defaults on save but the
     * in-memory model returned to the caller would still read those
     * attributes as null until re-fetched.
     */
    protected $attributes = [
        'shop_name' => 'Welcome Foodcity',
        'thank_you_message' => 'Thank you for shopping with us!',
        'paper_size' => 'thermal',
        'receipt_width' => '80mm',
        'header_alignment' => 'center',
        'footer_alignment' => 'center',
        'receipt_margin' => 8,
        'receipt_padding' => 12,
        'font_family' => 'sans-serif',
        'font_size' => 12,
        'font_weight' => 'normal',
    ];

    /**
     * There is only ever one row. Get it, creating the default row the
     * first time anything asks for it.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
