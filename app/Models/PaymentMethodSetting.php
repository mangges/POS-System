<?php

namespace App\Models;

use App\Enum\Payments\PaymentMethod;
use App\Enum\Payments\QrisMode;
use Illuminate\Database\Eloquent\Model;

class PaymentMethodSetting extends Model
{
    protected $fillable = [
        'method',
        'is_active',
        'qris_mode',
        'qris_static_string',
        'qris_static_image_path',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'method' => PaymentMethod::class,
        'qris_mode' => QrisMode::class,
    ];

    public static function forMethod(PaymentMethod|string $method): ?self
    {
        $value = $method instanceof PaymentMethod ? $method->value : $method;

        return static::where('method', $value)->first();
    }
}
