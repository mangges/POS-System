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
use Illuminate\Support\Carbon;
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

    public function test_monthly_grouping_produces_one_row_per_calendar_month_for_the_same_product(): void
    {
        $this->actingAs(User::factory()->create());

        $category = Category::create(['name' => 'Snacks', 'slug' => 'snacks']);
        $chips = Product::create(['category_id' => $category->id, 'name' => 'Chips', 'price' => 10000, 'has_recipe' => false, 'stock' => 100]);

        $thisMonthOrder = $this->makeOrder(OrderStatus::Completed->value);
        $thisMonthOrder->forceFill(['created_at' => Carbon::now()])->save();
        OrderItem::create(['order_id' => $thisMonthOrder->id, 'product_id' => $chips->id, 'quantity' => 3, 'price' => 10000, 'subtotal' => 30000]);

        $lastMonthOrder = $this->makeOrder(OrderStatus::Completed->value);
        $lastMonthOrder->forceFill(['created_at' => Carbon::now()->subMonthNoOverflow()])->save();
        OrderItem::create(['order_id' => $lastMonthOrder->id, 'product_id' => $chips->id, 'quantity' => 2, 'price' => 10000, 'subtotal' => 20000]);

        $rangeStart = Carbon::now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d');
        $rangeEnd = Carbon::now()->format('Y-m-d');

        $rows = Livewire::test(ProductReport::class)
            ->set('data.period_type', 'monthly')
            ->set('data.date_range', "{$rangeStart} - {$rangeEnd}")
            ->instance()
            ->getRows();

        $this->assertCount(2, $rows);

        $thisMonthKey = Carbon::now()->format('Y-m');
        $lastMonthKey = Carbon::now()->subMonthNoOverflow()->format('Y-m');

        $thisMonthRow = $rows->firstWhere('period', $thisMonthKey);
        $lastMonthRow = $rows->firstWhere('period', $lastMonthKey);

        $this->assertNotNull($thisMonthRow);
        $this->assertNotNull($lastMonthRow);
        $this->assertEquals(30000.0, $thisMonthRow['revenue']);
        $this->assertEquals(20000.0, $lastMonthRow['revenue']);

        // Rows are sorted period-ascending when periods differ.
        $this->assertSame($lastMonthKey, $rows->first()['period']);
        $this->assertSame($thisMonthKey, $rows->last()['period']);
    }

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

        // RFC 4180 CSV may quote fields with spaces; validate header columns exist
        $this->assertStringContainsString('Period', $content);
        $this->assertStringContainsString('Product', $content);
        $this->assertStringContainsString('Qty Sold', $content);
        $this->assertStringContainsString('Revenue', $content);
        $this->assertStringContainsString('Chips', $content);
    }

    public function test_csv_export_escapes_product_names_that_look_like_formulas(): void
    {
        $this->actingAs(User::factory()->create());

        $category = Category::create(['name' => 'Snacks', 'slug' => 'snacks']);
        $product = Product::create(['category_id' => $category->id, 'name' => '=cmd|"/c calc"!A1', 'price' => 10000, 'has_recipe' => false, 'stock' => 100]);
        $completed = $this->makeOrder(OrderStatus::Completed->value);
        OrderItem::create(['order_id' => $completed->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 10000, 'subtotal' => 10000]);

        $test = Livewire::test(ProductReport::class)->callAction('exportCsv');

        $content = base64_decode(data_get($test->effects, 'download.content'));

        $this->assertStringNotContainsString('"=cmd', $content);
        $this->assertStringContainsString("'=cmd", $content);
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
}
