<?php

namespace App\Filament\Resources\Tables\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Meja')
                    ->placeholder('Contoh: Meja 1, VIP 3')
                    ->required()
                    ->maxLength(100),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'available' => 'Available',
                        'occupied'  => 'Occupied',
                        'reserved'  => 'Reserved',
                    ])
                    ->default('available')
                    ->required(),
            ]);
    }
}
