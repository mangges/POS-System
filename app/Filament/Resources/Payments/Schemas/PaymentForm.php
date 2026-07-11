<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('order_id')
                    ->relationship('order', 'id')
                    ->required(),
                Select::make('payment_method')
                    ->options(['qris' => 'Qris', 'transfer' => 'Transfer', 'cash' => 'Cash'])
                    ->required(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('transaction_id'),
                Select::make('status')
                    ->options(['pending' => 'Pending', 'success' => 'Success', 'failed' => 'Failed'])
                    ->default('pending')
                    ->required(),
            ]);
    }
}
