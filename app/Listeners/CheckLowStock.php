<?php

namespace App\Listeners;

use App\Events\StockChanged;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\RawMaterials\RawMaterialResource;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class CheckLowStock
{
    public function handle(StockChanged $event): void
    {
        $stockable = $event->stockable;

        if ($stockable->stock > $stockable->min_stock) {
            return;
        }

        $url = $stockable instanceof Product
            ? ProductResource::getUrl('index')
            : RawMaterialResource::getUrl('index');

        Notification::make()
            ->title('Stok menipis: ' . $stockable->name)
            ->body('Sisa stok: ' . $stockable->stock)
            ->danger()
            ->actions([Action::make('view')->label('Lihat')->url($url)])
            ->broadcast(User::all())
            ->sendToDatabase(User::all());
    }
}