<?php

namespace Tests\Feature\Models;

use App\Enum\Orders\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderObserverTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(): Order
    {
        return Order::create([
            'order_number' => 'ORD-1',
            'total_amount' => 0,
            'tax' => 0,
            'discount' => 0,
            'status' => OrderStatus::Pending,
            'order_type' => 'dine_in',
        ]);
    }

    public function test_completing_an_order_decrements_product_stock_for_non_recipe_product(): void
    {
        $category = Category::create(['name' => 'Snacks', 'slug' => 'snacks']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Chips',
            'price' => 10000,
            'has_recipe' => false,
            'stock' => 20,
        ]);

        $order = $this->makeOrder();
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'price' => 10000,
            'subtotal' => 30000,
        ]);

        $order->update(['status' => OrderStatus::Completed]);

        $this->assertEquals(17, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => 'product',
            'reference_id' => $product->id,
            'type' => 'out',
            'quantity' => '3.00',
        ]);
    }

    public function test_completing_an_order_decrements_raw_material_stock_via_recipe(): void
    {
        $category = Category::create(['name' => 'Drinks', 'slug' => 'drinks']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Latte',
            'price' => 25000,
            'has_recipe' => true,
        ]);
        $unit = Unit::create(['name' => 'Milliliter', 'symbol' => 'ml']);
        $milk = RawMaterial::create(['name' => 'Milk', 'unit_id' => $unit->id, 'stock' => 1000]);
        $coffee = RawMaterial::create(['name' => 'Coffee', 'unit_id' => $unit->id, 'stock' => 500]);
        Recipe::create(['product_id' => $product->id, 'raw_material_id' => $milk->id, 'unit_id' => $unit->id, 'quantity' => 150]);
        Recipe::create(['product_id' => $product->id, 'raw_material_id' => $coffee->id, 'unit_id' => $unit->id, 'quantity' => 20]);

        $order = $this->makeOrder();
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 25000,
            'subtotal' => 50000,
        ]);

        $order->update(['status' => OrderStatus::Completed]);

        $this->assertEquals('700.00', $milk->fresh()->stock);
        $this->assertEquals('460.00', $coffee->fresh()->stock);
    }

    public function test_resaving_an_already_completed_order_does_not_double_deduct(): void
    {
        $category = Category::create(['name' => 'Snacks', 'slug' => 'snacks']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Chips',
            'price' => 10000,
            'has_recipe' => false,
            'stock' => 20,
        ]);

        $order = $this->makeOrder();
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'price' => 10000,
            'subtotal' => 30000,
        ]);

        $order->update(['status' => OrderStatus::Completed]);
        $order->update(['customer_name' => 'Budi']);

        $this->assertEquals(17, $product->fresh()->stock);
        $this->assertEquals(1, StockMovement::count());
    }
}
