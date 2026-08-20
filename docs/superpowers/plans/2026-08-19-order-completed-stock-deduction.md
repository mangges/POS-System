# Decrease Stock on Order Completed Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When an `Order` transitions to `Completed` — from either `OrderService::finalizeOrder()` or a direct Filament edit — automatically insert `out` `StockMovement` rows for every item sold, deducting `raw_materials.stock` (via `Recipe`) or `products.stock` depending on `has_recipe`.

**Architecture:** An `OrderObserver::updated()` hook detects the status transition and inserts `StockMovement` rows inside a `DB::transaction`. Stock decrement itself is already handled by the existing `StockMovementObserver` (created previous session) — this task only ever creates `StockMovement` records, never touches `stock` columns directly.

**Tech Stack:** Laravel 12, PHPUnit (plain `TestCase` classes, not Pest — confirmed prior sessions).

## Global Constraints

- Never decrement `stock` directly in this observer — always go through `StockMovement::create()` so the existing `StockMovementObserver` does it, per the spec's single-source-of-truth rule.
- Guard with `$order->wasChanged('status')` so re-saving an already-`Completed` order never double-deducts.

---

### Task 1: `OrderObserver` — deduct stock when order completes

**Files:**
- Create: `app/Observers/OrderObserver.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Models/OrderObserverTest.php`

**Interfaces:**
- Consumes: `Order::items()` (existing `HasMany` to `OrderItem`), `OrderItem::product()` (existing `BelongsTo`), `Product::recipes()` (existing `HasMany` to `Recipe`, added prior session), `Recipe::raw_material_id`/`quantity`, `StockMovement::create()` (existing, triggers `StockMovementObserver` which does the actual `stock` increment/decrement).
- Produces: no new public interface — side effect only (inserts `StockMovement` rows when an `Order`'s `status` becomes `OrderStatus::Completed`).

- [ ] **Step 1: Write the observer**

```php
<?php

namespace App\Observers;

use App\Enum\Orders\OrderStatus;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status') || $order->status !== OrderStatus::Completed) {
            return;
        }

        DB::transaction(function () use ($order) {
            foreach ($order->items()->with('product.recipes')->get() as $item) {
                $product = $item->product;

                if ($product->has_recipe) {
                    foreach ($product->recipes as $recipe) {
                        StockMovement::create([
                            'reference_id' => $recipe->raw_material_id,
                            'reference_type' => 'raw_material',
                            'type' => 'out',
                            'quantity' => $recipe->quantity * $item->quantity,
                            'user_id' => $order->user_id,
                            'notes' => "Order {$order->order_number}",
                        ]);
                    }
                } else {
                    StockMovement::create([
                        'reference_id' => $product->id,
                        'reference_type' => 'product',
                        'type' => 'out',
                        'quantity' => $item->quantity,
                        'user_id' => $order->user_id,
                        'notes' => "Order {$order->order_number}",
                    ]);
                }
            }
        });
    }
}
```

- [ ] **Step 2: Register the observer**

In `app/Providers/AppServiceProvider.php`, add import `use App\Models\Order;` and `use App\Observers\OrderObserver;`, then in `boot()` (after the existing `StockMovement::observe(...)` line):

```php
        Order::observe(OrderObserver::class);
```

- [ ] **Step 3: Write failing tests**

```php
<?php

namespace Tests\Feature\Models;

use App\Enum\Orders\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Recipe;
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
        $this->assertEquals(1, \App\Models\StockMovement::count());
    }
}
```

- [ ] **Step 4: Run tests to verify they fail**

Run: `php artisan test --filter=OrderObserverTest`
Expected: FAIL (`stock` unchanged — observer not wired yet)

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=OrderObserverTest`
Expected: PASS (all 3 tests)

- [ ] **Step 6: Full regression + commit**

Run: `php artisan test`
Expected: all PASS except the pre-existing unrelated `ExampleTest` failure (302 on `/`, confirmed pre-existing in prior sessions).

```bash
git add app/Observers/OrderObserver.php app/Providers/AppServiceProvider.php tests/Feature/Models/OrderObserverTest.php
git commit -m "feat: deduct raw material/product stock when an order completes"
```
