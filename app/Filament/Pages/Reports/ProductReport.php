<?php

namespace App\Filament\Pages\Reports;

use App\Enum\Orders\OrderStatus;
use App\Filament\Pages\Reports\Concerns\HasReportPeriod;
use App\Models\OrderItem;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use League\Csv\Writer;
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedTableCells)
                ->action(fn () => $this->exportCsv()),
            Action::make('exportPdf')
                ->label('Export PDF')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->action(fn () => $this->exportPdf()),
        ];
    }

    protected function exportCsv()
    {
        $rows = $this->getRows();

        $csv = Writer::createFromString('');
        $csv->insertOne(['Period', 'Product', 'Qty Sold', 'Revenue']);

        foreach ($rows as $row) {
            $csv->insertOne([
                $row['period'],
                $row['product_name'],
                $row['quantity'],
                $row['revenue'],
            ]);
        }

        return response()->streamDownload(
            fn () => print $csv->toString(),
            'product-report-' . now()->format('Ymd-His') . '.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    protected function exportPdf()
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reports.product-report', [
            'rows' => $this->getRows(),
            'periodType' => $this->periodType(),
        ]);

        return response()->streamDownload(
            fn () => print $pdf->output(),
            'product-report-' . now()->format('Ymd-His') . '.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
