<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Enum\Orders\OrderStatus;
use App\Events\OrderPlaced;
use App\Events\OrderStatusUpdated;
use App\Notifications\OrderStatusPushNotification;
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

            if ($order->table_id) {
                broadcast(new OrderPlaced($order));
            }
        });

        static::updated(function (Order $order) {
            if ($order->wasChanged('status') && $order->table()->first()?->qr_token) {
                if ($order->guestSession?->isExpired()) {
                    $order->guestSession->update(['expires_at' => now()->addMinutes(15)]);
                }

                $event = new OrderStatusUpdated($order);
                broadcast($event);

                if ($order->guestSession) {
                    $order->guestSession->notify(new OrderStatusPushNotification($event));
                }
            }
        });
    }

    protected $fillable = [
        'order_number',
        'table_id',
        'customer_id',
        'customer_name',
        'user_id',
        'shift_id',
        'guest_session_id',
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

    public function guestSession(): BelongsTo
    {
        return $this->belongsTo(GuestSession::class);
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

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function getCustomerDisplayNameAttribute(): ?string
    {
        return $this->customer?->name ?? $this->customer_name;
    }
}
