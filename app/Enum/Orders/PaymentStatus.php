<?php

namespace App\Enum\Orders;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Failed = 'failed';

    public function label(): string
    {
        return match($this) {
            self::Unpaid => 'Unpaid',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Unpaid => 'yellow',
            self::Paid => 'green',
            self::Failed => 'red',
        };
    }
}