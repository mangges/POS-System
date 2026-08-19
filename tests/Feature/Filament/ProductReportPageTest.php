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
