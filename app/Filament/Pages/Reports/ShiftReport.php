<?php

namespace App\Filament\Pages\Reports;

use App\Enum\Orders\PaymentStatus;
use App\Enum\Payments\PaymentMethod;
use App\Enum\Shifts\CashMovementType;
use App\Filament\Pages\Reports\Concerns\ExportsExcel;
use App\Filament\Pages\Reports\Concerns\HasReportPeriod;
use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

class ShiftReport extends Page
{
    use ExportsExcel;
    use HasReportPeriod;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.reports.shift-report';

    public ?array $data = [];

    public function getTitle(): string
    {
        return __('shift-report.Shift Report');
    }

    public static function getNavigationLabel(): string
    {
        return __('shift-report.Shift Report');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.Reports');
    }

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
                Select::make('user_id')
                    ->label('Kasir')
                    ->options(fn () => User::pluck('name', 'id'))
                    ->placeholder('Semua kasir')
                    ->live(),
            ]);
    }

    /** @return Collection<int, array{user: string, opened_at: string, closed_at: ?string, opening_cash: float, cash_sales: float, non_cash_sales: float, cash_in: float, cash_out: float, expected_cash: ?float, actual_cash: ?float, difference: ?float}> */
    public function getRows(): Collection
    {
        [$start, $end] = $this->dateRange();
        $userId = $this->data['user_id'] ?? null;

        return Shift::query()
            ->with(['user', 'orders.payment', 'cashMovements'])
            ->whereBetween('opened_at', [$start, $end])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->get()
            ->map(function (Shift $shift) {
                $paidOrders = $shift->orders->filter(
                    fn (Order $order) => $order->payment && $order->payment->status === PaymentStatus::Success
                );

                $cashSales = (float) $paidOrders
                    ->filter(fn (Order $order) => $order->payment->payment_method === PaymentMethod::Cash->value)
                    ->sum('total_amount');

                $nonCashSales = (float) $paidOrders
                    ->filter(fn (Order $order) => $order->payment->payment_method !== PaymentMethod::Cash->value)
                    ->sum('total_amount');

                $cashIn = (float) $shift->cashMovements->where('type', CashMovementType::In)->sum('amount');
                $cashOut = (float) $shift->cashMovements->where('type', CashMovementType::Out)->sum('amount');

                return [
                    'user' => $shift->user->name,
                    'opened_at' => $shift->opened_at->format('Y-m-d H:i'),
                    'closed_at' => $shift->closed_at?->format('Y-m-d H:i'),
                    'opening_cash' => (float) $shift->opening_cash,
                    'cash_sales' => $cashSales,
                    'non_cash_sales' => $nonCashSales,
                    'cash_in' => $cashIn,
                    'cash_out' => $cashOut,
                    'expected_cash' => $shift->expected_cash !== null ? (float) $shift->expected_cash : null,
                    'actual_cash' => $shift->actual_cash !== null ? (float) $shift->actual_cash : null,
                    'difference' => $shift->difference !== null ? (float) $shift->difference : null,
                ];
            })
            ->sortByDesc('opened_at')
            ->values();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon(Heroicon::OutlinedTableCells)
                ->action(fn () => $this->exportExcelReport()),
            Action::make('exportPdf')
                ->label('Export PDF')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->action(fn () => $this->exportPdf()),
        ];
    }

    protected function exportExcelReport()
    {
        $rows = $this->getRows();

        return $this->exportExcel(
            filenamePrefix: 'shift-report',
            headers: ['Kasir', 'Dibuka', 'Ditutup', 'Modal Awal', 'Cash Sales', 'Non-Cash Sales', 'Cash In', 'Cash Out', 'Expected', 'Actual', 'Selisih'],
            rows: $rows->map(fn (array $row) => [
                $row['user'],
                $row['opened_at'],
                $row['closed_at'] ?? '-',
                $row['opening_cash'],
                $row['cash_sales'],
                $row['non_cash_sales'],
                $row['cash_in'],
                $row['cash_out'],
                $row['expected_cash'] ?? '-',
                $row['actual_cash'] ?? '-',
                $row['difference'] ?? '-',
            ]),
            moneyColumns: [4, 5, 6, 7, 8, 9, 10, 11],
            totalLabel: 'Total',
            totalValue: $rows->sum('cash_sales') + $rows->sum('non_cash_sales'),
        );
    }

    protected function exportPdf()
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reports.shift-report', [
            'rows' => $this->getRows(),
            'periodType' => $this->periodType(),
        ]);

        return response()->streamDownload(
            fn () => print $pdf->output(),
            'shift-report-' . now()->format('Ymd-His') . '.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
