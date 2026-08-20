<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class DashboardStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $startDate = null;
        $endDate = null;
        
        if (!empty($this->filters['date_range'])) {
            $dates = explode(' - ', $this->filters['date_range']);
            if (count($dates) === 2) {
                try {
                    $startDate = \Carbon\Carbon::createFromFormat('d/m/Y', trim($dates[0]))->startOfDay();
                    $endDate = \Carbon\Carbon::createFromFormat('d/m/Y', trim($dates[1]))->endOfDay();
                } catch (\Exception $e) {
                    $startDate = null;
                    $endDate = null;
                }
            }
        }

        $userQuery = \App\Models\User::query();
        $orderQuery = \App\Models\Order::query();
        $productQuery = \App\Models\Product::query();
        $customerQuery = \App\Models\Customer::query();

        if ($startDate) {
            $userQuery->whereDate('created_at', '>=', $startDate);
            $orderQuery->whereDate('created_at', '>=', $startDate);
            $productQuery->whereDate('created_at', '>=', $startDate);
            $customerQuery->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $userQuery->whereDate('created_at', '<=', $endDate);
            $orderQuery->whereDate('created_at', '<=', $endDate);
            $productQuery->whereDate('created_at', '<=', $endDate);
            $customerQuery->whereDate('created_at', '<=', $endDate);
        }

        return [
            Stat::make('Total Users', $userQuery->count()),
            Stat::make('Total Orders', $orderQuery->count()),
            Stat::make('Total Products', $productQuery->count()),
            Stat::make('Total Customers', $customerQuery->count()),
        ];
    }
}
