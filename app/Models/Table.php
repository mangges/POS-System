<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Table extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'number',
        'barcode',
        'qr_token',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    // ---------------------------------------------------------------------------
    // Boot: auto-generate qr_token on creation
    // ---------------------------------------------------------------------------

    protected static function booted(): void
    {
        static::creating(function (Table $table) {
            if (empty($table->qr_token)) {
                $table->qr_token = Str::random(16);
            }
            // Back-fill "name" from legacy "number" if not provided
            if (empty($table->name) && ! empty($table->number)) {
                $table->name = $table->number;
            }
        });
    }

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function qrCode()
    {
        return $this->hasOne(QrCode::class);
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Returns the full self-order URL that will be encoded into the QR image.
     * Uses qr_token (not table PK) so tokens can be rotated without losing
     * historical order data.
     */
    public function getOrderUrl(): string
    {
        return route('order', ['table_token' => $this->qr_token]);
    }

    /**
     * Rotate the qr_token in-place and save.
     * Call this BEFORE regenerating the QR image.
     */
    public function rotateToken(): void
    {
        $this->qr_token = Str::random(16);
        $this->save();
    }
}
