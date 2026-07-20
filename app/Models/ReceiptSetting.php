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
     * There is only ever one row. Get it, creating the default row the
     * first time anything asks for it.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
