<?php

namespace App\Observers;

use App\Events\StockChanged;
use App\Models\StockMovement;

class StockMovementObserver
{
    public function created(StockMovement $movement): void
    {
        $column = $movement->type === 'in' ? 'increment' : 'decrement';

        $movement->reference()->{$column}('stock', $movement->quantity);

        StockChanged::dispatch($movement->reference()->first());
    }
}
