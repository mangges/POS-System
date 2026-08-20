<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Enum\Orders\OrderStatus;
use App\Events\OrderStatusUpdated;
use Filament\Notifications\Notification;

class Order extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            Notification::make()
                ->title('Order baru: ' . $order->order_number)
                ->body($order->customer_name . ' — Rp ' . number_format((float) $order->total_amount, 0, ',', '.'))
                ->broadcast(User::all())
                ->sendToDatabase(User::all());
        });

        static::updated(function (Order $order) {
            if ($order->wasChanged('status') && $order->table()->first()?->qr_token) {
                broadcast(new OrderStatusUpdated($order));
            }
        });
    }

    protected $fillable = [
        'order_number',
        'table_id',
        'customer_id',
        'user_id',
        'total_amount',
        'tax',
        'discount',
        'status',
        'payment_id',
        'order_type',
        'customer_name',
        'token',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'total_amount' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
