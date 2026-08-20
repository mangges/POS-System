<?php

namespace App\Filament\Pages\Reports;

use App\Enum\Orders\OrderStatus;
use App\Enum\Payments\PaymentMethod;
use App\Filament\Pages\Reports\Concerns\HasReportPeriod;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use League\Csv\EscapeFormula;
use League\Csv\Writer;
use UnitEnum;

class SalesReport extends Page
{
    use HasReportPeriod;

    protected static ?string $title = 'Sales Report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.reports.sales-report';

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

    /** @return Collection<int, array{period: string, payment_method: string, order_count: int, subtotal: float, tax: float, total: float}> */
    public function getRows(): Collection
    {
        [$start, $end] = $this->dateRange();

        $orders = Order::query()
            ->with('payment')
            ->where('status', OrderStatus::Completed)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        return $orders
            ->groupBy(fn (Order $order) => $this->periodKey($order->created_at) . '|' . ($order->payment?->payment_method ?? 'unknown'))
            ->map(function (Collection $ordersInGroup) {
                $first = $ordersInGroup->first();
                $paymentMethod = PaymentMethod::tryFrom($first->payment?->payment_method ?? '');

                return [
                    'period' => $this->periodKey($first->created_at),
                    'payment_method' => $paymentMethod?->label() ?? 'Unknown',
                    'order_count' => $ordersInGroup->count(),
                    'subtotal' => (float) $ordersInGroup->sum(fn (Order $o) => $o->total_amount - $o->tax),
                    'tax' => (float) $ordersInGroup->sum('tax'),
                    'total' => (float) $ordersInGroup->sum('total_amount'),
                ];
            })
            ->values()
            ->sort(fn (array $a, array $b) => $a['period'] === $b['period']
                ? $a['payment_method'] <=> $b['payment_method']
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
        $csv->addFormatter(new EscapeFormula());
        $csv->insertOne(['Period', 'Payment Method', 'Orders', 'Subtotal', 'Tax', 'Total']);

        foreach ($rows as $row) {
            $csv->insertOne([
                $row['period'],
                $row['payment_method'],
                $row['order_count'],
                $row['subtotal'],
                $row['tax'],
                $row['total'],
            ]);
        }

        return response()->streamDownload(
            fn () => print $csv->toString(),
            'sales-report-' . now()->format('Ymd-His') . '.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    protected function exportPdf()
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reports.sales-report', [
            'rows' => $this->getRows(),
            'periodType' => $this->periodType(),
        ]);

        return response()->streamDownload(
            fn () => print $pdf->output(),
            'sales-report-' . now()->format('Ymd-His') . '.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
