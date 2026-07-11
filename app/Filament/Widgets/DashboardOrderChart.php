<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Order;

class DashboardOrderChart extends ChartWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full'; 
    
    protected ?string $heading = 'Orders';

    protected function getData(): array
    {
        $monthName =  [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];

        $rawData = Order::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->whereYear('created_at', 2026)
            ->groupBy('month')
            ->pluck('count', 'month');

        $data = [];
        $labels = [];
        
        foreach ($monthName as $number => $name) {
            $labels[] = $name;
            $data[] = $rawData[$number] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Orders created',
                    'data' => $data,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

}
