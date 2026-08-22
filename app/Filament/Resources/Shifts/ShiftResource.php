<?php

namespace App\Filament\Resources\Shifts;

use App\Filament\Resources\Shifts\Pages\ListShifts;
use App\Filament\Resources\Shifts\Pages\ViewShift;
use App\Filament\Resources\Shifts\RelationManagers\CashMovementsRelationManager;
use App\Filament\Resources\Shifts\Tables\ShiftsTable;
use App\Models\Shift;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Transaction';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('user.name')->label('Kasir'),
            TextEntry::make('opened_at')->dateTime(),
            TextEntry::make('closed_at')->dateTime()->placeholder('Masih berjalan'),
            TextEntry::make('opening_cash')->money('IDR'),
            TextEntry::make('expected_cash')->money('IDR')->placeholder('-'),
            TextEntry::make('actual_cash')->money('IDR')->placeholder('-'),
            TextEntry::make('difference')->money('IDR')->placeholder('-')
                ->color(fn (?string $state): string => match (true) {
                    $state === null => 'gray',
                    (float) $state === 0.0 => 'success',
                    default => 'danger',
                }),
            TextEntry::make('status')->badge(),
            TextEntry::make('note')->placeholder('-')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return ShiftsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CashMovementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShifts::route('/'),
            'view' => ViewShift::route('/{record}'),
        ];
    }
}
