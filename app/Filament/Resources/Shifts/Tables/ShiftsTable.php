<?php

namespace App\Filament\Resources\Shifts\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShiftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Kasir')
                    ->searchable(),
                TextColumn::make('opened_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->dateTime()
                    ->placeholder('Masih berjalan')
                    ->sortable(),
                TextColumn::make('opening_cash')
                    ->money('IDR'),
                TextColumn::make('expected_cash')
                    ->money('IDR')
                    ->placeholder('-'),
                TextColumn::make('actual_cash')
                    ->money('IDR')
                    ->placeholder('-'),
                TextColumn::make('difference')
                    ->money('IDR')
                    ->placeholder('-')
                    ->color(fn (?string $state): string => match (true) {
                        $state === null => 'gray',
                        (float) $state === 0.0 => 'success',
                        default => 'danger',
                    }),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->defaultSort('opened_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
