<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['customer', 'payment']))
            ->columns([
                TextColumn::make('order_number')
                    ->searchable(),
                TextColumn::make('table.id')
                    ->searchable(),
                TextColumn::make('customer_display_name')
                    ->label('Customer')
                    ->searchable(['customer_name']),
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('total_amount')
                    ->money('Rp.')
                    ->sortable(),
                TextColumn::make('tax')
                    ->money('Rp.')
                    ->sortable(),
                TextColumn::make('discount')
                    ->money('Rp.')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('payment.status')
                    ->badge(),
                TextColumn::make('order_type')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
