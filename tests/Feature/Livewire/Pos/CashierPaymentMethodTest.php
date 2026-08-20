<?php

namespace Tests\Feature\Livewire\Pos;

use App\Enum\Orders\OrderStatus;
use App\Enum\Orders\PaymentStatus;
use App\Livewire\Pos\Cashier;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethodSetting;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashierPaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_methods_are_excluded_from_active_methods(): void
    {
        $this->actingAs(User::factory()->create());

        PaymentMethodSetting::where('method', 'transfer')->update(['is_active' => false]);

        Livewire::test(Cashier::class)
            ->assertSet('activeMethods', ['cash', 'qris']);
    }

    public function test_default_payment_method_falls_back_when_cash_is_inactive(): void
    {
        $this->actingAs(User::factory()->create());

        PaymentMethodSetting::where('method', 'cash')->update(['is_active' => false]);

        Livewire::test(Cashier::class)
            ->assertSet('paymentMethod', 'qris');
    }

    public function test_qris_image_is_null_when_mode_is_edc(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Cashier::class)
            ->set('paymentMethod', 'qris')
            ->assertSet('qrisImage', null);
    }

    public function test_qris_image_renders_svg_when_mode_is_dynamic(): void
    {
        $this->actingAs(User::factory()->create());

        PaymentMethodSetting::where('method', 'qris')->update([
            'qris_mode' => 'dynamic',
            'qris_static_string' => '0002010102112614TESTMERCHANT015204581253033605802ID5909TOKO TEST6007JAKARTA63041066',
        ]);

        Livewire::test(Cashier::class)
            ->set('paymentMethod', 'qris')
            ->assertSet('qrisImage', fn (?string $svg) => $svg !== null && str_contains($svg, '<svg'));
    }

    public function test_finalize_order_rejects_a_deactivated_payment_method_set_by_the_client(): void
    {
        $this->actingAs(User::factory()->create());

        $category = \App\Models\Category::create(['name' => 'Food', 'slug' => 'food']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Nasi Goreng',
            'price' => 20000,
            'has_recipe' => false,
            'stock' => 10,
            'is_out_of_stock' => false,
            'destination' => 'kitchen',
            'is_active' => true,
        ]);

        PaymentMethodSetting::where('method', 'transfer')->update(['is_active' => false]);

        Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->set('customerName', 'Test Customer')
            ->call('checkout')
            // Bypass the UI gating and force a deactivated method directly,
            // simulating a manipulated Livewire request from an untrusted client.
            ->set('paymentMethod', 'transfer')
            ->set('cashReceived', 30000)
            ->call('finalizeOrder');

        $order = Order::where('status', \App\Enum\Orders\OrderStatus::Completed)->latest('id')->first();

        $this->assertNotNull($order);
        $this->assertNotSame('transfer', $order->payment->payment_method);
    }

    private function createTestProduct(): Product
    {
        $category = \App\Models\Category::create(['name' => 'Food', 'slug' => 'food']);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Nasi Goreng',
            'price' => 20000,
            'has_recipe' => false,
            'stock' => 10,
            'is_out_of_stock' => false,
            'destination' => 'kitchen',
            'is_active' => true,
        ]);
    }

    public function test_finalize_order_is_blocked_for_qris_without_payment_confirmation(): void
    {
        $this->actingAs(User::factory()->create());

        $product = $this->createTestProduct();

        Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->set('customerName', 'Test Customer')
            ->call('checkout')
            ->set('paymentMethod', 'qris')
            ->call('finalizeOrder');

        $order = Order::where('customer_name', 'Test Customer')->latest('id')->first();

        $this->assertNotNull($order);
        $this->assertNotSame(\App\Enum\Orders\OrderStatus::Completed, $order->status);
    }

    public function test_finalize_order_succeeds_for_qris_once_payment_is_confirmed(): void
    {
        $this->actingAs(User::factory()->create());

        $product = $this->createTestProduct();

        Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->set('customerName', 'Test Customer')
            ->call('checkout')
            ->set('paymentMethod', 'qris')
            ->set('paymentConfirmed', true)
            ->call('finalizeOrder');

        $order = Order::where('customer_name', 'Test Customer')->latest('id')->first();

        $this->assertSame(\App\Enum\Orders\OrderStatus::Completed, $order->status);
        $this->assertSame(\App\Enum\Orders\PaymentStatus::Success, $order->payment->status);
    }

    public function test_switching_payment_method_resets_payment_confirmation(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Cashier::class)
            ->set('paymentMethod', 'qris')
            ->set('paymentConfirmed', true)
            ->set('paymentMethod', 'transfer')
            ->assertSet('paymentConfirmed', false);
    }

    private function createPendingQrisTableOrder(): Order
    {
        $table = Table::create(['number' => '1', 'barcode' => 'T1']);

        $order = Order::create([
            'order_number' => 'ORD000999',
            'table_id' => $table->id,
            'customer_name' => 'Table Customer',
            'total_amount' => 55500,
            'tax' => 5500,
            'status' => OrderStatus::Pending,
            'order_type' => 'dine-in',
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'qris',
            'amount' => 0,
            'status' => PaymentStatus::Pending,
        ]);

        $order->update(['payment_id' => $payment->id]);

        return $order;
    }

    public function test_accept_table_order_is_blocked_for_qris_without_payment_confirmation(): void
    {
        $this->actingAs(User::factory()->create());

        $order = $this->createPendingQrisTableOrder();

        Livewire::test(Cashier::class)->call('acceptTableOrder', $order->id);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_accept_table_order_succeeds_for_qris_once_payment_is_confirmed(): void
    {
        $this->actingAs(User::factory()->create());

        $order = $this->createPendingQrisTableOrder();

        Livewire::test(Cashier::class)
            ->set("confirmedPayments.{$order->id}", true)
            ->call('acceptTableOrder', $order->id);

        $this->assertSame(OrderStatus::Processing, $order->fresh()->status);
    }

    public function test_finalize_table_order_completes_a_ready_order(): void
    {
        $this->actingAs(User::factory()->create());

        $order = $this->createPendingQrisTableOrder();
        $order->update(['status' => OrderStatus::Ready]);

        Livewire::test(Cashier::class)
            ->call('finalizeTableOrder', $order->id)
            ->assertSet('showPaymentModal', true)
            ->call('finalizeOrder');

        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Success, $order->fresh()->payment->status);
    }
}
