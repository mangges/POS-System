<?php

namespace App\Enum\Orders;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum OrderStatus: string implements HasLabel, HasColor
{
    case Pending        = 'pending';
    case Completed      = 'completed';
    case Cancelled      = 'cancelled';

    public function getLabel(): string
    {
        return match($this) {
            self::Pending        => 'Pending',
            self::Completed      => 'Completed',
            self::Cancelled      => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::Pending        => 'warning',
            self::Completed      => 'success',
            self::Cancelled      => 'danger',
        };
    }
}