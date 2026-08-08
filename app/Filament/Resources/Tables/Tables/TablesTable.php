<?php

namespace App\Filament\Resources\Tables\Tables;

use App\Services\QrCodeService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class TablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Meja')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'occupied'  => 'danger',
                        'reserved'  => 'warning',
                        default     => 'gray',
                    }),

                // QR thumbnail via the related qrCode model
                ImageColumn::make('qrCode.file_path')
                    ->label('QR Code')
                    ->disk('public')
                    ->size(64)
                    ->square(),

                TextColumn::make('qr_token')
                    ->label('Token')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),

                // ----------------------------------------------------------------
                // Download QR PNG action
                // ----------------------------------------------------------------
                Action::make('download_qr')
                    ->label('Download QR')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn ($record) => $record->qrCode !== null)
                    ->url(fn ($record) => $record->qrCode
                        ? Storage::disk('public')->url($record->qrCode->file_path)
                        : '#'
                    )
                    ->openUrlInNewTab(),

                // ----------------------------------------------------------------
                // Re-generate QR action — requires confirmation before execution
                // ----------------------------------------------------------------
                Action::make('regenerate_qr')
                    ->label('Re-generate QR')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Re-generate QR Code?')
                    ->modalDescription(
                        'QR Code lama akan menjadi tidak valid setelah proses ini. '
                        . 'Pelanggan yang sudah scan QR lama harus scan ulang. '
                        . 'Apakah Anda yakin ingin melanjutkan?'
                    )
                    ->modalSubmitActionLabel('Ya, Re-generate')
                    ->action(function ($record) {
                        try {
                            app(QrCodeService::class)->regenerate($record);

                            Notification::make()
                                ->title('QR Code berhasil di-generate ulang')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal re-generate QR Code')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
