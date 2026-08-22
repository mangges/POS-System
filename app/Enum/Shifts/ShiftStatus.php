<?php

namespace App\Enum\Shifts;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum ShiftStatus: string implements HasLabel, HasColor
{
    case Open   = 'open';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match($this) {
            self::Open   => 'Open',
            self::Closed => 'Closed',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::Open   => 'success',
            self::Closed => 'gray',
        };
    }
}
