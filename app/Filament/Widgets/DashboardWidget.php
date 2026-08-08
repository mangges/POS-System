<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Order;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;

class DashboardWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;
    
    protected function getStats(): array
    {
        return [
            ...$this->getTotalOrders(),
            Stat::make('Customers', $this->getCustomers()),
            Stat::make('Users', $this->getUsers()),
        ];
    }

    private function getTotalOrders(): array
    {
        $currentMonth = Order::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        $pastMonth =  Order::whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereYear('created_at', Carbon::now()->subMonth()->year)
            ->count();

        if($pastMonth > 0) {
            $percentageChange = round((($currentMonth - $pastMonth) / $pastMonth) * 100, 1);
        } else {
            $percentageChange = $currentMonth > 0 ? 100 : 0;
        }

        $increase = $percentageChange >= 0;
        
        return [
            Stat::make('Total Orders', $currentMonth)
                ->description(abs($percentageChange) . '% ' . ($increase ? 'increase' : 'decrease') . ' from'. Carbon::now()->subMonth()->format('F'))
                ->descriptionIcon($increase ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->descriptionColor($increase ? 'success' : 'danger'),
        ];
    }

    private function getCustomers(): int
    {
        return Customer::count();
    }

    private function getUsers(): int
    {
        return User::count();
    }
}
