<?php

namespace App\Filament\Pages\Reports;

use App\Enum\Orders\OrderStatus;
use App\Filament\Pages\Reports\Concerns\HasReportPeriod;
use App\Models\OrderItem;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

class ProductReport extends Page
{
    use HasReportPeriod;

    protected static ?string $title = 'Product Report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.reports.product-report';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['period_type' => 'daily']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                $this->periodTypeField(),
                $this->dateRangeField(),
            ]);
    }

    /** @return Collection<int, array{period: string, product_name: string, quantity: float, revenue: float}> */
    public function getRows(): Collection
    {
        [$start, $end] = $this->dateRange();

        $items = OrderItem::query()
            ->with(['product', 'order'])
            ->whereHas('order', function ($query) use ($start, $end) {
                $query->where('status', OrderStatus::Completed)
                    ->whereBetween('created_at', [$start, $end]);
            })
            ->get();

        return $items
            ->groupBy(fn (OrderItem $item) => $this->periodKey($item->order->created_at) . '|' . $item->product_id)
            ->map(function (Collection $itemsInGroup) {
                $first = $itemsInGroup->first();

                return [
                    'period' => $this->periodKey($first->order->created_at),
                    'product_name' => $first->product->name,
                    'quantity' => (float) $itemsInGroup->sum('quantity'),
                    'revenue' => (float) $itemsInGroup->sum('subtotal'),
                ];
            })
            ->values()
            ->sort(fn (array $a, array $b) => $a['period'] === $b['period']
                ? $b['revenue'] <=> $a['revenue']
                : $a['period'] <=> $b['period'])
            ->values();
    }
}
