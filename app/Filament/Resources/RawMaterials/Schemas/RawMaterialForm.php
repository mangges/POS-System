<?php

namespace App\Filament\Resources\RawMaterials\Schemas;

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
                TextInput::make('unit')
                    ->required(),
                TextInput::make('stock')
                    ->required()
                    ->numeric()
                    ->default(0.0),
            ]);
    }
}
