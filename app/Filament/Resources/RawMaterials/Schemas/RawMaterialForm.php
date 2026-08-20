<?php

namespace App\Filament\Resources\RawMaterials\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RawMaterialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('unit_id')
                    ->relationship('unit', 'symbol')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('stock')
                    ->required()
                    ->numeric()
                    ->default(0.0),
            ]);
    }
}
