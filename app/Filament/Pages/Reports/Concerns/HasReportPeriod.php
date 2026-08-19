<?php

namespace App\Filament\Pages\Reports\Concerns;

use Filament\Forms\Components\Radio;
use Illuminate\Support\Carbon;
use Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker;

trait HasReportPeriod
{
    protected function periodTypeField(): Radio
    {
        return Radio::make('period_type')
            ->label('Period')
            ->options([
                'daily' => 'Daily',
                'monthly' => 'Monthly',
                'annual' => 'Annual',
            ])
            ->default('daily')
            ->inline()
            ->live();
    }

    protected function dateRangeField(): DateRangePicker
    {
        return DateRangePicker::make('date_range')
            ->label('Date Range')
            ->format('Y-m-d', true)
            ->startDate(now()->startOfMonth())
            ->endDate(now())
            ->live();
    }

    protected function periodType(): string
    {
        return $this->data['period_type'] ?? 'daily';
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function dateRange(): array
    {
        $value = $this->data['date_range'] ?? null;

        if (! $value) {
            return [now()->startOfMonth()->startOfDay(), now()->endOfDay()];
        }

        [$start, $end] = explode(' - ', $value);

        return [
            Carbon::createFromFormat('Y-m-d', $start)->startOfDay(),
            Carbon::createFromFormat('Y-m-d', $end)->endOfDay(),
        ];
    }

    protected function periodKey(Carbon $date): string
    {
        return match ($this->periodType()) {
            'monthly' => $date->format('Y-m'),
            'annual' => $date->format('Y'),
            default => $date->format('Y-m-d'),
        };
    }
}
