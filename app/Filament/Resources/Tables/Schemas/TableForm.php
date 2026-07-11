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
                TextInput::make('number')
                    ->required(),
                TextInput::make('barcode')
                    ->required(),
                Select::make('status')
                    ->options(['available' => 'Available', 'occupied' => 'Occupied'])
                    ->default('available')
                    ->required(),
            ]);
    }
}
