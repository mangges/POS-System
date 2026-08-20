<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enum\Orders\OrderStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('order_number')
                    ->required(),
                Select::make('table_id')
                    ->relationship('table', 'id'),
                Select::make('customer_id')
                    ->relationship('customer', 'name'),
                Select::make('user_id')
                    ->relationship('user', 'name'),
                TextInput::make('total_amount')
                    ->required()
                    ->numeric(),
                TextInput::make('tax')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('discount')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                Select::make('status')
                    ->options(OrderStatus::class)
                    ->default(OrderStatus::Pending)
                    ->required(),
                Select::make('payment_id')
                    ->relationship('payment', 'transaction_id')
                    ->getOptionLabelFromRecordUsing(
                        fn ($record) => "{$record->payment_method} - {$record->status->getLabel()} ({$record->transaction_id})"
                    )
                    ->searchable(),
                Select::make('order_type')
                    ->options(['dine-in' => 'Dine in', 'takeaway' => 'Takeaway'])
                    ->default('dine-in')
                    ->required(),
            ]);
    }
}
