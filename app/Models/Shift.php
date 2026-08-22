<?php

namespace App\Models;

use App\Enum\Shifts\ShiftStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'opening_cash',
        'expected_cash',
        'actual_cash',
        'difference',
        'status',
        'note',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'status' => ShiftStatus::class,
        'opening_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'actual_cash' => 'decimal:2',
        'difference' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
