# Sales & Product Reports — Design

## Purpose

Admin needs daily/monthly/annual reporting on sales and products sold, exportable to PDF and CSV/Excel, for closing and business review.

## Scope

Two separate Filament pages, not a combined dashboard:

1. **Sales Report** — revenue and order counts over time, broken down by payment method.
2. **Product Report** — quantity sold and revenue per product over time.

Out of scope: stock movement report (may be added later as a third page following the same pattern).

## Revenue recognition rule

Only `Order` rows with `status = Completed` count toward revenue and product quantities. `Pending` and `Cancelled` orders are excluded — matches existing stock-deduction behavior (stock is only deducted on order completion, see `OrderObserver`), and avoids reporting revenue that could later be reversed by a cancellation.

## Period toggle

Both pages share one filter control:

- **Period type**: Daily / Monthly / Annual (radio/select), controls the row grouping.
- **Date range**: `Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker` (already used on `App\Filament\Pages\Dashboard`), filters `orders.created_at`.
- Default: Daily, current month to date.

Grouping SQL per period type (MySQL):
- Daily: `DATE(orders.created_at)`
- Monthly: `DATE_FORMAT(orders.created_at, '%Y-%m')`
- Annual: `YEAR(orders.created_at)`

Shared via a small trait `App\Filament\Pages\Reports\Concerns\HasReportPeriod` (period + date range form fields, and a `groupByExpression(): string` helper) — used by both report pages to avoid duplicating the filter form and grouping logic.

## Sales Report

**Page**: `App\Filament\Pages\Reports\SalesReport`

**Query**: `Order::where('status', OrderStatus::Completed)->whereBetween('created_at', [start, end])`, left join `payments` on `orders.payment_id = payments.id`, grouped by period expression.

**Columns**:
- Period (formatted per period type, e.g. `2026-08-19`, `2026-08`, `2026`)
- Order count
- Total revenue (`SUM(orders.total_amount)`)
- Revenue by payment method: Cash / QRIS / Transfer (`SUM(orders.total_amount)` conditioned on `payments.payment_method`, via conditional aggregation)

**Footer row**: grand totals for order count and each revenue column.

## Product Report

**Page**: `App\Filament\Pages\Reports\ProductReport`

**Query**: `OrderItem` joined to `orders` (`status = Completed`, date range on `orders.created_at`) and `products` (for name), grouped by period expression + `product_id`.

**Columns**:
- Period
- Product name
- Quantity sold (`SUM(order_items.quantity)`)
- Revenue (`SUM(order_items.subtotal)`)

Sorted by period, then revenue descending within period.

## Rendering

Both pages render a plain HTML table (Tailwind classes matching existing Filament admin styling) inside the page's Blade view — not the Filament Table/Resource component, since the data is a pre-aggregated array of rows (no Eloquent primary key per row), which the Table framework isn't built for.

## Export

Two header actions on each page, exporting exactly the rows currently visible (same filtered/grouped query, not the whole dataset):

- **CSV**: built with `league/csv` (already a transitive dependency via `filament/actions`, no new package). Streamed via Livewire `streamDownload()`.
- **PDF**: rendered from a dedicated Blade view (`resources/views/pdf/reports/sales-report.blade.php`, `product-report.blade.php`) via `barryvdh/laravel-dompdf` — **new composer dependency**, added because no PDF renderer exists in the project yet. Streamed via `streamDownload()`.

Both export actions reuse the same data-fetching method the page view uses, so exported numbers always match what's on screen.

## Navigation

Both pages added under a new "Reports" nav group in the Filament sidebar (`App\Providers\Filament\AdminPanelProvider` via `discoverPages`, standard convention already used for other custom pages — no explicit registration needed beyond the group label set on each page class via `protected static ?string $navigationGroup`).

## Testing

One Pest/PHPUnit feature test per report page asserting: a completed order in range appears with correct totals, a pending/cancelled order in range is excluded, and CSV export returns a 200 response with expected header row. Follows existing test patterns in the repo (check `tests/Feature` for conventions before writing).

## Commit
use /commit-work, don't commit after you finish the task, give me time to try firs
commit with oneline and no co-author