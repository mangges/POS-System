<?php

namespace App\Enum\Orders;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'Pending',
            self::Success => 'success',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending => 'yellow',
            self::Success => 'green',
            self::Failed => 'red',
        };
    }
}