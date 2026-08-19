<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                Toggle::make('has_recipe')
                    ->live()
                    ->required(),
                Repeater::make('recipes')
                    ->relationship()
                    ->schema([
                        Select::make('raw_material_id')
                            ->relationship('rawMaterial', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('unit_id')
                            ->relationship('unit', 'symbol')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('quantity')
                            ->required()
                            ->numeric(),
                    ])
                    ->columns(3)
                    ->defaultItems(1)
                    ->visible(fn (Get $get) => $get('has_recipe'))
                    ->columnSpanFull(),
                TextInput::make('stock')
                    ->numeric(),
                Toggle::make('is_out_of_stock')
                    ->required(),
                Select::make('destination')
                    ->options([
                        'kitchen' => 'Kitchen', 
                        'bar' => 'Bar', 
                        'cashier' => 'Cashier'
                        ])
                    ->default('kitchen')
                    ->required(),
                FileUpload::make('image')
                    ->image(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
