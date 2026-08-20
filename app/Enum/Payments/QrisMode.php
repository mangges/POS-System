<?php

namespace App\Enum\Payments;

enum QrisMode: string
{
    case Edc = 'edc';
    case Dynamic = 'dynamic';

    public function label(): string
    {
        return match ($this) {
            self::Edc => 'EDC',
            self::Dynamic => 'Static → Dynamic (generate otomatis)',
        };
    }
}
