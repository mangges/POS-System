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
use UnitEnum;

class SalesReport extends Page
{
    use HasReportPeriod;

    protected static ?string $title = 'Sales Report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

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


    /** @return Collection<int, array{period: string, order_count: int, total_revenue: float, cash_revenue: float, qris_revenue: float, transfer_revenue: float}> */
    public function getRows(): Collection
    {
        [$start, $end] = $this->dateRange();

        $orders = Order::query()
            ->with('payment')
            ->where('status', OrderStatus::Completed)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        return $orders
            ->groupBy(fn (Order $order) => $this->periodKey($order->created_at))
            ->map(function (Collection $ordersInPeriod, string $period) {
                return [
                    'period' => $period,
                    'order_count' => $ordersInPeriod->count(),
                    'total_revenue' => (float) $ordersInPeriod->sum('total_amount'),
                    'cash_revenue' => (float) $ordersInPeriod
                        ->filter(fn (Order $o) => $o->payment?->payment_method === PaymentMethod::Cash->value)
                        ->sum('total_amount'),
                    'qris_revenue' => (float) $ordersInPeriod
                        ->filter(fn (Order $o) => $o->payment?->payment_method === PaymentMethod::Qris->value)
                        ->sum('total_amount'),
                    'transfer_revenue' => (float) $ordersInPeriod
                        ->filter(fn (Order $o) => $o->payment?->payment_method === PaymentMethod::Transfer->value)
                        ->sum('total_amount'),
                ];
            })
            ->sortKeys()
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

        $output = 'Period,Orders,Total Revenue,Cash,QRIS,Transfer' . "\n";

        foreach ($rows as $row) {
            $output .= implode(',', [
                $row['period'],
                $row['order_count'],
                $row['total_revenue'],
                $row['cash_revenue'],
                $row['qris_revenue'],
                $row['transfer_revenue'],
            ]) . "\n";
        }

        return response()->streamDownload(
            fn () => print $output,
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
