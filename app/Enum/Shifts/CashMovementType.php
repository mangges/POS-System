<?php

namespace App\Enum\Shifts;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum CashMovementType: string implements HasLabel, HasColor
{
    case In  = 'in';
    case Out = 'out';

    public function getLabel(): string
    {
        return match($this) {
            self::In  => 'Cash In',
            self::Out => 'Cash Out',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::In  => 'success',
            self::Out => 'danger',
        };
    }
}
