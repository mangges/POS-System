<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state)),
                TextInput::make('pin')
                    ->default(null),
                Select::make('role')
                    ->options(['admin' => 'Admin', 'cashier' => 'Cashier'])
                    ->default('cashier')
                    ->required()
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Select $component, $record) {
                        if ($record) {
                            $component->state($record->getRoleNames()->first());
                        }
                    }),
            ]);
    }
}
