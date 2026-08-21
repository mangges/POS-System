<?php

namespace Tests\Feature\Livewire\Pos;

use App\Livewire\Pos\Cashier;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashierSplitBillTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(string $name, float $price): Product
    {
        $category = Category::firstOrCreate(['slug' => 'food'], ['name' => 'Food']);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'price' => $price,
            'has_recipe' => false,
            'stock' => 10,
            'is_out_of_stock' => false,
            'destination' => 'kitchen',
            'is_active' => true,
        ]);
    }

    public function test_assigning_a_unit_moves_it_out_of_the_unassigned_pool(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id) // qty 1
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id);

        $this->assertSame(0, $component->instance()->unassignedQty($product->id));
        $this->assertSame(20000.0, $component->instance()->splitGroupSubtotal(0));
    }

    public function test_qty_can_be_split_across_groups(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Es Teh', 5000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('incrementQuantity', 0) // qty 3
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('assignUnitToGroup', 1, $product->id);

        $this->assertSame(0, $component->instance()->unassignedQty($product->id));
        $this->assertSame(5000.0, $component->instance()->splitGroupSubtotal(0));
        $this->assertSame(10000.0, $component->instance()->splitGroupSubtotal(1));
    }

    public function test_cannot_assign_more_units_than_the_cart_line_has(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Es Teh', 5000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id) // qty 1
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id); // no unassigned units left

        $this->assertSame(1, $component->instance()->splitGroups[0]['assignments'][$product->id]);
        $this->assertArrayNotHasKey($product->id, $component->instance()->splitGroups[1]['assignments']);
    }

    public function test_checkout_split_is_blocked_until_every_unit_is_assigned(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Es Teh', 5000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id); // only 1 of 2 assigned

        $this->assertFalse($component->instance()->canCheckoutSplit());
    }

    public function test_checkout_split_requires_at_least_two_named_groups(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Es Teh', 5000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('addSplitGroup', 'Andi')
            ->call('assignUnitToGroup', 0, $product->id);

        $this->assertFalse($component->instance()->canCheckoutSplit());
    }

    public function test_removing_a_cart_item_clears_its_assignments(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Es Teh', 5000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('removeFromCart', 0);

        $this->assertArrayNotHasKey($product->id, $component->instance()->splitGroups[0]['assignments']);
    }

    public function test_toggle_split_mode_off_clears_groups(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Es Teh', 5000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('addSplitGroup', 'Andi')
            ->call('toggleSplitMode') // on -> off is a second call; do it twice below
            ->call('toggleSplitMode');

        $this->assertSame([], $component->instance()->splitGroups);
    }

    public function test_unassign_unit_from_group_returns_it_to_the_pool(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Es Teh', 5000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('unassignUnitFromGroup', 0, $product->id);

        $this->assertSame(1, $component->instance()->unassignedQty($product->id));
        $this->assertArrayNotHasKey($product->id, $component->instance()->splitGroups[0]['assignments']);
    }

    public function test_remove_split_group_reindexes_remaining_groups(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Es Teh', 5000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('addSplitGroup', 'Citra')
            ->call('removeSplitGroup', 0); // drop Andi

        $this->assertCount(2, $component->instance()->splitGroups);
        $this->assertSame('Budi', $component->instance()->splitGroups[0]['name']);
        $this->assertSame('Citra', $component->instance()->splitGroups[1]['name']);
    }

    public function test_build_split_cart_items_returns_correct_array_shape(): void
    {
        $this->actingAs(User::factory()->create());
        $product1 = $this->createProduct('Nasi Goreng', 20000);
        $product2 = $this->createProduct('Es Teh', 5000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product1->id)
            ->call('addToCart', $product2->id)
            ->call('incrementQuantity', 0) // Nasi Goreng qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('assignUnitToGroup', 0, $product1->id)
            ->call('assignUnitToGroup', 0, $product1->id)
            ->call('assignUnitToGroup', 0, $product2->id);

        $items = $component->instance()->buildSplitCartItems(0);

        $this->assertCount(2, $items);
        $this->assertSame($product1->id, $items[0]['id']);
        $this->assertSame('Nasi Goreng', $items[0]['name']);
        $this->assertEquals(20000, (int)$items[0]['price']);
        $this->assertSame(2, $items[0]['qty']);
        $this->assertEquals(40000, (int)$items[0]['subtotal']);
        $this->assertSame($product2->id, $items[1]['id']);
        $this->assertSame('Es Teh', $items[1]['name']);
        $this->assertEquals(5000, (int)$items[1]['price']);
        $this->assertSame(1, $items[1]['qty']);
        $this->assertEquals(5000, (int)$items[1]['subtotal']);
    }

    public function test_qty_decrease_without_removal_trims_stale_assignments(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Es Teh', 5000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('incrementQuantity', 0) // qty 3
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 0, $product->id) // all 3 units to group 0
            ->call('decrementQuantity', 0) // qty back to 2, should trim assignments to 2
            ->call('decrementQuantity', 0); // qty back to 1, should trim assignments to 1

        $this->assertSame(1, $component->instance()->splitGroups[0]['assignments'][$product->id]);
        $this->assertSame(5000.0, $component->instance()->splitGroupSubtotal(0));
        $items = $component->instance()->buildSplitCartItems(0);
        $this->assertCount(1, $items);
        $this->assertSame(1, $items[0]['qty']);
        $this->assertEquals(5000, (int)$items[0]['subtotal']);
    }

    public function test_checkout_split_creates_one_order_per_group(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->assertSet('cart', [])
            ->assertSet('splitMode', false)
            ->assertSet('splitGroups', []);

        $andi = \App\Models\Order::where('customer_name', 'Andi')->first();
        $budi = \App\Models\Order::where('customer_name', 'Budi')->first();

        $this->assertNotNull($andi);
        $this->assertNotNull($budi);
        $this->assertSame(1, $andi->items()->count());
        $this->assertSame(20000.0, (float) $andi->items()->first()->subtotal);
        $this->assertSame(\App\Enum\Orders\OrderStatus::Pending, $andi->status);
        $this->assertNull($andi->table_id);
        $this->assertNotNull($andi->payment);
        $this->assertSame(\App\Enum\Orders\PaymentStatus::Pending, $andi->payment->status);
    }

    public function test_checkout_split_does_nothing_when_not_ready(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('addSplitGroup', 'Andi') // only 1 group, still unassigned
            ->call('checkoutSplit');

        $this->assertSame(0, \App\Models\Order::count());
    }

    public function test_a_split_order_can_be_paid_from_the_drafts_list(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('checkoutSplit');

        $andi = \App\Models\Order::where('customer_name', 'Andi')->first();

        Livewire::test(Cashier::class)
            ->call('finalizeTableOrder', $andi->id)
            ->assertSet('showPaymentModal', true)
            ->set('cashReceived', 25000)
            ->call('finalizeOrder');

        $this->assertSame(\App\Enum\Orders\OrderStatus::Completed, $andi->fresh()->status);
    }
}
