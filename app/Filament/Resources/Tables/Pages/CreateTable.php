<?php

namespace App\Filament\Resources\Tables\Pages;

use App\Filament\Resources\Tables\TableResource;
use App\Services\QrCodeService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateTable extends CreateRecord
{
    protected static string $resource = TableResource::class;

    /**
     * After the Table record is saved, immediately generate its QR code.
     * The qr_token has already been auto-set by the model's booted() hook.
     */
    protected function afterCreate(): void
    {
        try {
            app(QrCodeService::class)->generate($this->record);

            Notification::make()
                ->title('QR Code berhasil dibuat')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Meja dibuat, tapi QR Code gagal di-generate')
                ->body($e->getMessage())
                ->warning()
                ->send();
        }
    }
}
