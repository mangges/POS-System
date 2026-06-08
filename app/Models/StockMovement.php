<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_id',
        'reference_type',
        'type',
        'quantity',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function reference()
    {
        if ($this->reference_type === 'product') {
            return $this->belongsTo(Product::class, 'reference_id');
        }
        
        return $this->belongsTo(RawMaterial::class, 'reference_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
