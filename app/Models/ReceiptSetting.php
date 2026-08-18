<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiptSetting extends Model
{
    protected $fillable = [
        'store_name',
        'address',
        'phone',
        'website',
        'logo_path',
        'footer_text',
        'show_qr',
    ];

    protected $casts = [
        'show_qr' => 'boolean',
    ];

    public static function current(): self
    {
        return static::first() ?? new static(['store_name' => 'Pos Cafee', 'show_qr' => true]);
    }
}
