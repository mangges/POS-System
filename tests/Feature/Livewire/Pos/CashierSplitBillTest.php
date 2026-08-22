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
            ->assertSet('showPaymentModal', true);

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

    public function test_checkout_split_opens_payment_modal_on_first_tab(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->assertSet('showPaymentModal', true)
            ->assertSet('activeSplitIndex', 0);

        $andi = \App\Models\Order::where('customer_name', 'Andi')->first();
        $budi = \App\Models\Order::where('customer_name', 'Budi')->first();

        $component->assertSet('currentOrderId', $andi->id);
        $this->assertSame($andi->id, $component->instance()->splitGroups[0]['order_id']);
        $this->assertFalse($component->instance()->splitGroups[0]['paid']);
        $this->assertSame($budi->id, $component->instance()->splitGroups[1]['order_id']);
        $this->assertFalse($component->instance()->splitGroups[1]['paid']);
    }

    public function test_a_split_order_can_be_paid_from_the_drafts_list(): void
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
            ->call('checkoutSplit');

        $andi = \App\Models\Order::where('customer_name', 'Andi')->first();

        Livewire::test(Cashier::class)
            ->call('finalizeTableOrder', $andi->id)
            ->assertSet('showPaymentModal', true)
            ->set('cashReceived', 25000)
            ->call('finalizeOrder');

        $this->assertSame(\App\Enum\Orders\OrderStatus::Completed, $andi->fresh()->status);
    }

    public function test_a_finalized_split_order_renders_its_receipt(): void
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
            ->call('checkoutSplit');

        $andi = \App\Models\Order::where('customer_name', 'Andi')->first();

        Livewire::test(Cashier::class)
            ->call('finalizeTableOrder', $andi->id)
            ->set('cashReceived', 25000)
            ->call('finalizeOrder');

        $this->assertNotNull($andi->fresh()->token);
        $this->get(route('receipt.show', ['token' => $andi->fresh()->token]))->assertOk();
    }

    public function test_splitting_a_loaded_draft_replaces_it_instead_of_duplicating_it(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->set('customerName', 'Meja 5')
            ->call('saveDraft');

        $original = \App\Models\Order::where('customer_name', 'Meja 5')->first();
        $this->assertNotNull($original);

        $component = Livewire::test(Cashier::class)
            ->call('loadDraft', $original->id)
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->assertSet('activeDraft', null);

        $this->assertSame(2, \App\Models\Order::count());
        $this->assertNull(\App\Models\Order::find($original->id));
        $andi = \App\Models\Order::where('customer_name', 'Andi')->first();
        $this->assertNotNull($andi);
        $this->assertNotNull(\App\Models\Order::where('customer_name', 'Budi')->first());
        $component->assertSet('currentOrderId', $andi->id);
    }

    public function test_split_groups_cannot_be_set_directly_from_the_client(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi');

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        $component->set('splitGroups', [
            ['name' => 'Andi', 'assignments' => [$product->id => 99]],
            ['name' => 'Budi', 'assignments' => [$product->id => 99]],
        ]);
    }

    public function test_switch_split_tab_changes_active_order_and_resets_payment_fields(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->set('paymentMethod', 'qris')
            ->set('paymentConfirmed', true)
            ->set('cashReceived', 20000);

        $budiOrderId = $component->instance()->splitGroups[1]['order_id'];

        $component->call('switchSplitTab', 1)
            ->assertSet('activeSplitIndex', 1)
            ->assertSet('currentOrderId', $budiOrderId)
            ->assertSet('paymentMethod', 'cash')
            ->assertSet('paymentConfirmed', false)
            ->assertSet('cashReceived', null);
    }

    public function test_switch_split_tab_ignores_invalid_index(): void
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
            ->call('switchSplitTab', 5)
            ->assertSet('activeSplitIndex', 0);
    }

    public function test_split_group_tax_and_total_match_group_subtotal(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id);

        $this->assertSame(2200.0, $component->instance()->splitGroupTax(0));
        $this->assertSame(22200.0, $component->instance()->splitGroupTotal(0));
    }

    public function test_finalize_order_in_split_mode_marks_tab_paid_and_advances_to_next_unpaid(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->set('cashReceived', 22200)
            ->call('finalizeOrder');

        $andi = \App\Models\Order::where('customer_name', 'Andi')->first();
        $budi = \App\Models\Order::where('customer_name', 'Budi')->first();

        $this->assertSame(\App\Enum\Orders\OrderStatus::Completed, $andi->fresh()->status);
        $this->assertSame(\App\Enum\Orders\OrderStatus::Pending, $budi->fresh()->status);

        $component->assertSet('showPaymentModal', true)
            ->assertSet('activeSplitIndex', 1)
            ->assertSet('currentOrderId', $budi->id);

        $this->assertTrue($component->instance()->splitGroups[0]['paid']);
        $this->assertFalse($component->instance()->splitGroups[1]['paid']);
    }

    public function test_finalize_order_on_last_split_tab_closes_modal_and_resets_state(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->set('cashReceived', 22200)
            ->call('finalizeOrder') // pays Andi, advances to Budi
            ->set('cashReceived', 22200)
            ->call('finalizeOrder'); // pays Budi, should close out

        $andi = \App\Models\Order::where('customer_name', 'Andi')->first();
        $budi = \App\Models\Order::where('customer_name', 'Budi')->first();

        $this->assertSame(\App\Enum\Orders\OrderStatus::Completed, $andi->fresh()->status);
        $this->assertSame(\App\Enum\Orders\OrderStatus::Completed, $budi->fresh()->status);

        $component->assertSet('showPaymentModal', false)
            ->assertSet('splitGroups', [])
            ->assertSet('splitMode', false)
            ->assertSet('activeSplitIndex', null)
            ->assertSet('cart', []);
    }

    public function test_finalize_order_cannot_repay_an_already_paid_split_tab(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->set('cashReceived', 22200)
            ->call('finalizeOrder'); // pays Andi, advances to Budi

        $andi = \App\Models\Order::where('customer_name', 'Andi')->first();
        $andiPaidAt = $andi->fresh()->updated_at;

        $component->call('switchSplitTab', 0) // go back to view Andi's now-paid tab
            ->set('cashReceived', 22200)
            ->call('finalizeOrder'); // must no-op

        $this->assertSame(\App\Enum\Orders\OrderStatus::Completed, $andi->fresh()->status);
        $this->assertTrue($andiPaidAt->equalTo($andi->fresh()->updated_at));
    }

    public function test_open_qris_preview_modal_uses_active_split_group_total(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->call('openQrisPreviewModal');

        $this->assertSame(22200, $component->instance()->previewQrisAmount);
    }

    public function test_open_qris_preview_modal_uses_cart_total_outside_split_mode(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('openQrisPreviewModal');

        $this->assertSame(22200, $component->instance()->previewQrisAmount);
    }
}
