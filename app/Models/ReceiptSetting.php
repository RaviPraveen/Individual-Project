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

    // Match the database defaults before a newly created model is reloaded.
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

    // Create the settings row on first use.
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
