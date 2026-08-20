<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public string $message;

    public string $type;

    public function __construct(public Order $order)
    {
        $this->message = match ($order->status) {
            \App\Enum\Orders\OrderStatus::Processing => "Pesanan {$order->order_number} dikonfirmasi, sedang disiapkan.",
            \App\Enum\Orders\OrderStatus::Ready => "Pesanan {$order->order_number} siap diantar.",
            \App\Enum\Orders\OrderStatus::Completed => "Pesanan {$order->order_number} selesai.",
            \App\Enum\Orders\OrderStatus::Cancelled => "Pesanan {$order->order_number} dibatalkan.",
            default => "Pesanan {$order->order_number} diperbarui.",
        };

        $this->type = match ($order->status) {
            \App\Enum\Orders\OrderStatus::Ready, \App\Enum\Orders\OrderStatus::Completed => 'success',
            \App\Enum\Orders\OrderStatus::Cancelled => 'danger',
            default => 'info',
        };
    }

    public function broadcastOn(): Channel
    {
        return new Channel('table.' . $this->order->table()->first()->qr_token);
    }

    public function broadcastAs(): string
    {
        return 'OrderStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'type' => $this->type,
            'order_id' => $this->order->id,
            'status' => $this->order->status->value,
            'token' => $this->order->token,
        ];
    }
}
