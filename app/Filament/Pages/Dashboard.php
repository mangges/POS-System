<?php

namespace App\Filament\Pages;

use Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->schema([
                    DateRangePicker::make('date_range')
                        ->label('Date Range')
                        ->allowInput(),
                ])
        ];
    }
}
