<?php

namespace App\Enum\Orders;

enum OrderStatus: string
{
    case Pending        = 'pending';
    case Processing     = 'processing';
    case Ready          = 'ready';
    case Completed      = 'completed';
    case Cancelled      = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Pending        => 'Pending',
            self::Processing     => 'Diproses',
            self::Ready          => 'Siap Diantar',
            self::Completed      => 'Completed',
            self::Cancelled      => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending        => 'yellow',
            self::Processing     => 'blue',
            self::Ready          => 'orange',
            self::Completed      => 'green',
            self::Cancelled      => 'red',
        };
    }
}