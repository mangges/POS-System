<?php

namespace App\Listeners;

use App\Events\StockChanged;
use App\Models\User;
use Filament\Notifications\Notification;

class CheckLowStock
{
    public function handle(StockChanged $event): void
    {
        $stockable = $event->stockable;

        if ($stockable->stock > $stockable->min_stock) {
            return;
        }

        Notification::make()
            ->title('Stok menipis: ' . $stockable->name)
            ->body('Sisa stok: ' . $stockable->stock)
            ->danger()
            ->broadcast(User::all())
            ->sendToDatabase(User::all());
    }
}