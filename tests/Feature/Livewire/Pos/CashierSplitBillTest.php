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
}
