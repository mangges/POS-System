<?php

namespace Tests\Feature\Livewire\LandingPage;

use App\Livewire\LandingPage\LandingPage;
use App\Models\Category;
use App\Models\GuestSession;
use App\Models\Order;
use App\Models\PaymentMethodSetting;
use App\Models\Product;
use App\Models\QrCode;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LandingPagePaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    private function createTable(): Table
    {
        return Table::create(['number' => '1', 'barcode' => 'T1']);
    }

    private function sessionTokenFor(Table $table): string
    {
        $qrCode = QrCode::create([
            'table_id' => $table->id,
            'qr_url' => 'https://example.test/order/' . $table->qr_token,
            'file_path' => 'qrcodes/table_' . $table->id . '.svg',
        ]);

        return GuestSession::startFor($qrCode)->token;
    }

    public function test_inactive_methods_are_excluded_from_active_methods(): void
    {
        $table = $this->createTable();

        PaymentMethodSetting::where('method', 'transfer')->update(['is_active' => false]);

        Livewire::test(LandingPage::class, ['session_token' => $this->sessionTokenFor($table)])
            ->assertSet('activeMethods', ['cash', 'qris']);
    }

    public function test_default_payment_method_falls_back_when_cash_is_inactive(): void
    {
        $table = $this->createTable();

        PaymentMethodSetting::where('method', 'cash')->update(['is_active' => false]);

        Livewire::test(LandingPage::class, ['session_token' => $this->sessionTokenFor($table)])
            ->assertSet('paymentMethod', 'qris');
    }

    public function test_qris_image_renders_svg_when_mode_is_dynamic(): void
    {
        $table = $this->createTable();

        PaymentMethodSetting::where('method', 'qris')->update([
            'qris_mode' => 'dynamic',
            'qris_static_string' => '0002010102112614TESTMERCHANT015204581253033605802ID5909TOKO TEST6007JAKARTA63041066',
        ]);

        Livewire::test(LandingPage::class, ['session_token' => $this->sessionTokenFor($table)])
            ->set('paymentMethod', 'qris')
            ->assertSet('qrisImage', fn (?string $svg) => $svg !== null && str_contains($svg, '<svg'));
    }

    public function test_checkout_rejects_a_deactivated_payment_method_set_by_the_client(): void
    {
        $table = $this->createTable();

        $category = Category::create(['name' => 'Food', 'slug' => 'food']);
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

        Livewire::test(LandingPage::class, ['session_token' => $this->sessionTokenFor($table)])
            ->call('addToCart', $product->id)
            ->set('customerName', 'Test Customer')
            // Bypass the UI gating and force a deactivated method directly, simulating
            // a manipulated Livewire request from an unauthenticated public client.
            ->set('paymentMethod', 'transfer')
            ->call('checkout');

        $order = Order::latest('id')->first();

        $this->assertNotNull($order);
        $this->assertNotSame('transfer', $order->payment->payment_method);
    }

    public function test_qris_qr_is_still_available_on_the_post_checkout_success_screen(): void
    {
        $table = $this->createTable();

        $category = Category::create(['name' => 'Food', 'slug' => 'food']);
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

        PaymentMethodSetting::where('method', 'qris')->update([
            'qris_mode' => 'dynamic',
            'qris_static_string' => '0002010102112614TESTMERCHANT015204581253033605802ID5909TOKO TEST6007JAKARTA63041066',
        ]);

        $test = Livewire::test(LandingPage::class, ['session_token' => $this->sessionTokenFor($table)])
            ->call('addToCart', $product->id)
            ->set('customerName', 'Test Customer')
            ->set('paymentMethod', 'qris')
            ->call('checkout')
            ->assertSet('orderSubmitted', true);

        // checkout() resets the cart, so $this->total is 0 by the time the success
        // screen renders. The captured lastOrderTotal must still let a QR be
        // generated for the real order amount, not for Rp 0.
        $lastOrderTotal = $test->get('lastOrderTotal');
        $this->assertGreaterThan(0, $lastOrderTotal);

        $svg = $test->instance()->qrisImageForAmount($lastOrderTotal);
        $this->assertNotNull($svg);
        $this->assertStringContainsString('<svg', $svg);
    }
}
