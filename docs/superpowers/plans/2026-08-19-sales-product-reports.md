# Sales & Product Reports Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add two Filament admin pages — Sales Report and Product Report — each with a Daily/Monthly/Annual period toggle and date range filter, exportable to CSV and PDF.

**Architecture:** Two standalone `Filament\Pages\Page` classes (not Resources — data is aggregated, not CRUD rows) under `app/Filament/Pages/Reports/`, sharing a `HasReportPeriod` trait for the filter form and period-key logic. Each page fetches the relevant Eloquent rows for the selected date range and groups them **in PHP** (not SQL `GROUP BY`/`DATE_FORMAT`) — the app runs MySQL in production but SQLite in tests (see `phpunit.xml`), and MySQL-only date functions would silently break on SQLite. Grouping via `Carbon`-formatted keys works identically on both. Export actions reuse the exact same `getRows()` method the on-screen table uses, so exported numbers always match what's displayed.

**Tech Stack:** Filament 5.6 (Pages, Actions, Forms), `malzariey/filament-daterangepicker-filter` (already installed), `league/csv` (already installed transitively via `filament/actions`), `barryvdh/laravel-dompdf` (new dependency, added in Task 1).

## Global Constraints

- Only `Order` rows with `status = OrderStatus::Completed` count toward revenue and product quantities (see `docs/superpowers/specs/2026-08-19-sales-product-reports-design.md`, "Revenue recognition rule").
- Both pages live under Filament nav group `'Reports'`.
- No new dependency for CSV (`league/csv` already present). PDF requires adding `barryvdh/laravel-dompdf`.
- Export actions must export exactly what's on screen (same filtered/grouped data), not the whole table.
- Do not commit after finishing implementation — leave the working tree for the user to test first. When asked to commit, use the `commit-work` skill, a one-line message, no `Co-Authored-By` trailer. (Per-task commits below still use plain `git commit` as part of the TDD cycle — the "don't commit" instruction applies to the final wrap-up, not to checkpoint commits during development. Confirm with the user before any final commit.)

---

### Task 1: Add PDF export dependency

**Files:**
- Modify: `composer.json`, `composer.lock` (via composer, not hand-edited)

**Interfaces:**
- Produces: `\Barryvdh\DomPDF\Facade\Pdf::loadView(string $view, array $data): \Barryvdh\DomPDF\PDF` — used by Task 3 and Task 5.

- [ ] **Step 1: Require the package**

Run: `composer require barryvdh/laravel-dompdf:^3.1`

- [ ] **Step 2: Verify the facade class resolves**

Run: `php artisan tinker --execute="echo class_exists(\Barryvdh\DomPDF\Facade\Pdf::class) ? 'ok' : 'missing';"`
Expected: `ok`

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: add barryvdh/laravel-dompdf for report PDF export"
```

---

### Task 2: Sales Report page (filters + table, no export yet)

**Files:**
- Create: `app/Filament/Pages/Reports/Concerns/HasReportPeriod.php`
- Create: `app/Filament/Pages/Reports/SalesReport.php`
- Create: `resources/views/filament/pages/reports/sales-report.blade.php`
- Test: `tests/Feature/Filament/SalesReportPageTest.php`

**Interfaces:**
- Produces (from `HasReportPeriod`, consumed by `SalesReport` in this task and `ProductReport` in Task 4):
  - `protected function periodTypeField(): \Filament\Forms\Components\Radio`
  - `protected function dateRangeField(): \Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker`
  - `protected function periodType(): string` — one of `'daily' | 'monthly' | 'annual'`, read from `$this->data['period_type']`
  - `protected function dateRange(): array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}` — `[$start, $end]`, read from `$this->data['date_range']`
  - `protected function periodKey(\Illuminate\Support\Carbon $date): string` — e.g. `'2026-08-19'`, `'2026-08'`, or `'2026'` depending on `periodType()`
- Produces (from `SalesReport`, consumed by Task 3):
  - `public function getRows(): \Illuminate\Support\Collection` — each element is `['period' => string, 'order_count' => int, 'total_revenue' => float, 'cash_revenue' => float, 'qris_revenue' => float, 'transfer_revenue' => float]`, sorted by period ascending.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/SalesReportPageTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Enum\Orders\OrderStatus;
use App\Filament\Pages\Reports\SalesReport;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesReportPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $status, ?string $paymentMethod = null, float $amount = 0): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'total_amount' => $amount,
            'tax' => 0,
            'discount' => 0,
            'status' => $status,
            'order_type' => 'dine_in',
        ]);

        if ($paymentMethod) {
            $payment = Payment::create([
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'status' => 'success',
            ]);

            $order->update(['payment_id' => $payment->id]);
        }

        return $order->fresh();
    }

    public function test_only_completed_orders_are_counted_and_grouped_by_payment_method(): void
    {
        $this->actingAs(User::factory()->create());

        $this->makeOrder(OrderStatus::Completed->value, 'cash', 50000);
        $this->makeOrder(OrderStatus::Completed->value, 'qris', 30000);
        $this->makeOrder(OrderStatus::Pending->value, 'cash', 99999);
        $this->makeOrder(OrderStatus::Cancelled->value, 'cash', 99999);

        $rows = Livewire::test(SalesReport::class)->instance()->getRows();

        $this->assertCount(1, $rows);
        $today = $rows->first();
        $this->assertSame(2, $today['order_count']);
        $this->assertEquals(80000.0, $today['total_revenue']);
        $this->assertEquals(50000.0, $today['cash_revenue']);
        $this->assertEquals(30000.0, $today['qris_revenue']);
        $this->assertEquals(0.0, $today['transfer_revenue']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SalesReportPageTest`
Expected: FAIL — `Class "App\Filament\Pages\Reports\SalesReport" not found`

- [ ] **Step 3: Create the shared period trait**

Create `app/Filament/Pages/Reports/Concerns/HasReportPeriod.php`:

```php
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
```

- [ ] **Step 4: Create the Sales Report page**

Create `app/Filament/Pages/Reports/SalesReport.php`:

```php
<?php

namespace App\Filament\Pages\Reports;

use App\Enum\Orders\OrderStatus;
use App\Enum\Payments\PaymentMethod;
use App\Filament\Pages\Reports\Concerns\HasReportPeriod;
use App\Models\Order;
use BackedEnum;
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
}
```

- [ ] **Step 5: Create the Blade view**

Create `resources/views/filament/pages/reports/sales-report.blade.php`:

```blade
<x-filament-panels::page>
    <div class="mb-4">
        {{ $this->form }}
    </div>

    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-x-auto">
        <table class="w-full text-start text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-white/10">
                    <th class="px-4 py-2 text-xs font-medium text-gray-500">Period</th>
                    <th class="px-4 py-2 text-xs font-medium text-gray-500">Orders</th>
                    <th class="px-4 py-2 text-xs font-medium text-gray-500">Total Revenue</th>
                    <th class="px-4 py-2 text-xs font-medium text-gray-500">Cash</th>
                    <th class="px-4 py-2 text-xs font-medium text-gray-500">QRIS</th>
                    <th class="px-4 py-2 text-xs font-medium text-gray-500">Transfer</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->getRows() as $row)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="px-4 py-2">{{ $row['period'] }}</td>
                        <td class="px-4 py-2">{{ $row['order_count'] }}</td>
                        <td class="px-4 py-2">{{ number_format($row['total_revenue'], 2) }}</td>
                        <td class="px-4 py-2">{{ number_format($row['cash_revenue'], 2) }}</td>
                        <td class="px-4 py-2">{{ number_format($row['qris_revenue'], 2) }}</td>
                        <td class="px-4 py-2">{{ number_format($row['transfer_revenue'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">No data for this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=SalesReportPageTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Pages/Reports/Concerns/HasReportPeriod.php app/Filament/Pages/Reports/SalesReport.php resources/views/filament/pages/reports/sales-report.blade.php tests/Feature/Filament/SalesReportPageTest.php
git commit -m "feat: add Sales Report page with daily/monthly/annual period filter"
```

---

### Task 3: Sales Report CSV & PDF export

**Files:**
- Modify: `app/Filament/Pages/Reports/SalesReport.php`
- Create: `resources/views/pdf/reports/sales-report.blade.php`
- Modify: `tests/Feature/Filament/SalesReportPageTest.php`

**Interfaces:**
- Consumes: `SalesReport::getRows()` from Task 2.
- Produces: header actions `exportCsv` and `exportPdf`, callable in tests via `Livewire::test(SalesReport::class)->callAction('exportCsv')`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Filament/SalesReportPageTest.php` (inside the class, after the existing test method):

```php
    public function test_csv_export_contains_header_row_and_data(): void
    {
        $this->actingAs(User::factory()->create());

        $this->makeOrder(OrderStatus::Completed->value, 'cash', 50000);

        $test = Livewire::test(SalesReport::class)->callAction('exportCsv');

        $test->assertFileDownloaded(contentType: 'text/csv');

        $content = base64_decode(data_get($test->effects, 'download.content'));

        $this->assertStringContainsString('Period,Orders,Total Revenue,Cash,QRIS,Transfer', $content);
        $this->assertStringContainsString('50000', $content);
    }

    public function test_pdf_export_returns_a_pdf(): void
    {
        $this->actingAs(User::factory()->create());

        $this->makeOrder(OrderStatus::Completed->value, 'cash', 50000);

        $test = Livewire::test(SalesReport::class)->callAction('exportPdf');

        $test->assertFileDownloaded(contentType: 'application/pdf');

        $content = base64_decode(data_get($test->effects, 'download.content'));

        $this->assertStringStartsWith('%PDF', $content);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=SalesReportPageTest`
Expected: FAIL — `exportCsv` / `exportPdf` actions not found

- [ ] **Step 3: Add export actions to the page**

Modify `app/Filament/Pages/Reports/SalesReport.php` — add imports and the `getHeaderActions()` + export methods:

```php
use Filament\Actions\Action;
use League\Csv\Writer;
```

(add alongside the existing `use` statements)

Add inside the `SalesReport` class, after `getRows()`:

```php
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
        $csv->insertOne(['Period', 'Orders', 'Total Revenue', 'Cash', 'QRIS', 'Transfer']);

        foreach ($rows as $row) {
            $csv->insertOne([
                $row['period'],
                $row['order_count'],
                $row['total_revenue'],
                $row['cash_revenue'],
                $row['qris_revenue'],
                $row['transfer_revenue'],
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
```

- [ ] **Step 4: Create the PDF Blade view**

Create `resources/views/pdf/reports/sales-report.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h1 { font-size: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 8px; text-align: left; }
        th { background-color: #f3f4f6; }
    </style>
</head>
<body>
    <h1>Sales Report ({{ ucfirst($periodType) }})</h1>
    <table>
        <thead>
            <tr>
                <th>Period</th>
                <th>Orders</th>
                <th>Total Revenue</th>
                <th>Cash</th>
                <th>QRIS</th>
                <th>Transfer</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['period'] }}</td>
                    <td>{{ $row['order_count'] }}</td>
                    <td>{{ number_format($row['total_revenue'], 2) }}</td>
                    <td>{{ number_format($row['cash_revenue'], 2) }}</td>
                    <td>{{ number_format($row['qris_revenue'], 2) }}</td>
                    <td>{{ number_format($row['transfer_revenue'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No data for this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=SalesReportPageTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Pages/Reports/SalesReport.php resources/views/pdf/reports/sales-report.blade.php tests/Feature/Filament/SalesReportPageTest.php
git commit -m "feat: add CSV and PDF export to Sales Report"
```

---

### Task 4: Product Report page (filters + table, no export yet)

**Files:**
- Create: `app/Filament/Pages/Reports/ProductReport.php`
- Create: `resources/views/filament/pages/reports/product-report.blade.php`
- Test: `tests/Feature/Filament/ProductReportPageTest.php`

**Interfaces:**
- Consumes: `HasReportPeriod` trait from Task 2 (same file, no changes needed).
- Produces (consumed by Task 5): `public function getRows(): \Illuminate\Support\Collection` — each element is `['period' => string, 'product_name' => string, 'quantity' => float, 'revenue' => float]`, sorted by period ascending, then revenue descending within period.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/ProductReportPageTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Enum\Orders\OrderStatus;
use App\Filament\Pages\Reports\ProductReport;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductReportPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $status): Order
    {
        return Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'total_amount' => 0,
            'tax' => 0,
            'discount' => 0,
            'status' => $status,
            'order_type' => 'dine_in',
        ]);
    }

    public function test_only_items_from_completed_orders_are_counted_and_grouped_by_product(): void
    {
        $this->actingAs(User::factory()->create());

        $category = Category::create(['name' => 'Snacks', 'slug' => 'snacks']);
        $chips = Product::create(['category_id' => $category->id, 'name' => 'Chips', 'price' => 10000, 'has_recipe' => false, 'stock' => 100]);
        $soda = Product::create(['category_id' => $category->id, 'name' => 'Soda', 'price' => 8000, 'has_recipe' => false, 'stock' => 100]);

        $completed = $this->makeOrder(OrderStatus::Completed->value);
        OrderItem::create(['order_id' => $completed->id, 'product_id' => $chips->id, 'quantity' => 3, 'price' => 10000, 'subtotal' => 30000]);
        OrderItem::create(['order_id' => $completed->id, 'product_id' => $soda->id, 'quantity' => 2, 'price' => 8000, 'subtotal' => 16000]);

        $pending = $this->makeOrder(OrderStatus::Pending->value);
        OrderItem::create(['order_id' => $pending->id, 'product_id' => $chips->id, 'quantity' => 99, 'price' => 10000, 'subtotal' => 990000]);

        $rows = Livewire::test(ProductReport::class)->instance()->getRows();

        $this->assertCount(2, $rows);

        $chipsRow = $rows->firstWhere('product_name', 'Chips');
        $this->assertEquals(3.0, $chipsRow['quantity']);
        $this->assertEquals(30000.0, $chipsRow['revenue']);

        // Sorted by revenue descending within the period: Chips (30000) before Soda (16000).
        $this->assertSame('Chips', $rows->first()['product_name']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductReportPageTest`
Expected: FAIL — `Class "App\Filament\Pages\Reports\ProductReport" not found`

- [ ] **Step 3: Create the Product Report page**

Create `app/Filament/Pages/Reports/ProductReport.php`:

```php
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
```

- [ ] **Step 4: Create the Blade view**

Create `resources/views/filament/pages/reports/product-report.blade.php`:

```blade
<x-filament-panels::page>
    <div class="mb-4">
        {{ $this->form }}
    </div>

    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-x-auto">
        <table class="w-full text-start text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-white/10">
                    <th class="px-4 py-2 text-xs font-medium text-gray-500">Period</th>
                    <th class="px-4 py-2 text-xs font-medium text-gray-500">Product</th>
                    <th class="px-4 py-2 text-xs font-medium text-gray-500">Qty Sold</th>
                    <th class="px-4 py-2 text-xs font-medium text-gray-500">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->getRows() as $row)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="px-4 py-2">{{ $row['period'] }}</td>
                        <td class="px-4 py-2">{{ $row['product_name'] }}</td>
                        <td class="px-4 py-2">{{ number_format($row['quantity'], 2) }}</td>
                        <td class="px-4 py-2">{{ number_format($row['revenue'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">No data for this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=ProductReportPageTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Pages/Reports/ProductReport.php resources/views/filament/pages/reports/product-report.blade.php tests/Feature/Filament/ProductReportPageTest.php
git commit -m "feat: add Product Report page with daily/monthly/annual period filter"
```

---

### Task 5: Product Report CSV & PDF export

**Files:**
- Modify: `app/Filament/Pages/Reports/ProductReport.php`
- Create: `resources/views/pdf/reports/product-report.blade.php`
- Modify: `tests/Feature/Filament/ProductReportPageTest.php`

**Interfaces:**
- Consumes: `ProductReport::getRows()` from Task 4.
- Produces: header actions `exportCsv` and `exportPdf`, same pattern as Task 3.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Filament/ProductReportPageTest.php` (inside the class):

```php
    public function test_csv_export_contains_header_row_and_data(): void
    {
        $this->actingAs(User::factory()->create());

        $category = Category::create(['name' => 'Snacks', 'slug' => 'snacks']);
        $chips = Product::create(['category_id' => $category->id, 'name' => 'Chips', 'price' => 10000, 'has_recipe' => false, 'stock' => 100]);
        $completed = $this->makeOrder(OrderStatus::Completed->value);
        OrderItem::create(['order_id' => $completed->id, 'product_id' => $chips->id, 'quantity' => 3, 'price' => 10000, 'subtotal' => 30000]);

        $test = Livewire::test(ProductReport::class)->callAction('exportCsv');

        $test->assertFileDownloaded(contentType: 'text/csv');

        $content = base64_decode(data_get($test->effects, 'download.content'));

        $this->assertStringContainsString('Period,Product,Qty Sold,Revenue', $content);
        $this->assertStringContainsString('Chips', $content);
    }

    public function test_pdf_export_returns_a_pdf(): void
    {
        $this->actingAs(User::factory()->create());

        $category = Category::create(['name' => 'Snacks', 'slug' => 'snacks']);
        $chips = Product::create(['category_id' => $category->id, 'name' => 'Chips', 'price' => 10000, 'has_recipe' => false, 'stock' => 100]);
        $completed = $this->makeOrder(OrderStatus::Completed->value);
        OrderItem::create(['order_id' => $completed->id, 'product_id' => $chips->id, 'quantity' => 3, 'price' => 10000, 'subtotal' => 30000]);

        $test = Livewire::test(ProductReport::class)->callAction('exportPdf');

        $test->assertFileDownloaded(contentType: 'application/pdf');

        $content = base64_decode(data_get($test->effects, 'download.content'));

        $this->assertStringStartsWith('%PDF', $content);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ProductReportPageTest`
Expected: FAIL — `exportCsv` / `exportPdf` actions not found

- [ ] **Step 3: Add export actions to the page**

Modify `app/Filament/Pages/Reports/ProductReport.php` — add imports:

```php
use Filament\Actions\Action;
use League\Csv\Writer;
```

Add inside the `ProductReport` class, after `getRows()`:

```php
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
```

- [ ] **Step 4: Create the PDF Blade view**

Create `resources/views/pdf/reports/product-report.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h1 { font-size: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 8px; text-align: left; }
        th { background-color: #f3f4f6; }
    </style>
</head>
<body>
    <h1>Product Report ({{ ucfirst($periodType) }})</h1>
    <table>
        <thead>
            <tr>
                <th>Period</th>
                <th>Product</th>
                <th>Qty Sold</th>
                <th>Revenue</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['period'] }}</td>
                    <td>{{ $row['product_name'] }}</td>
                    <td>{{ number_format($row['quantity'], 2) }}</td>
                    <td>{{ number_format($row['revenue'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No data for this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=ProductReportPageTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Run the full test suite**

Run: `php artisan test`
Expected: PASS (no regressions)

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Pages/Reports/ProductReport.php resources/views/pdf/reports/product-report.blade.php tests/Feature/Filament/ProductReportPageTest.php
git commit -m "feat: add CSV and PDF export to Product Report"
```

**Stop here.** Do not run `/commit-work`, squash, or push — the user wants to try the feature in the browser first (log into `/admin`, check the new "Reports" nav group, both pages, both export buttons) before any final commit/cleanup.
