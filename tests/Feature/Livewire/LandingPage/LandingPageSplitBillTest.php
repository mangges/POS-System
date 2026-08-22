<?php

namespace Tests\Feature\Livewire\LandingPage;

use App\Livewire\LandingPage\LandingPage;
use App\Models\Category;
use App\Models\GuestSession;
use App\Models\Order;
use App\Models\Product;
use App\Models\QrCode;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LandingPageSplitBillTest extends TestCase
{
    use RefreshDatabase;

    private function createGuestSession(): GuestSession
    {
        $table = Table::create(['number' => '1', 'barcode' => 'T1']);

        $qrCode = QrCode::create([
            'table_id' => $table->id,
            'qr_url' => 'https://example.test/order/' . $table->qr_token,
            'file_path' => 'qrcodes/table_' . $table->id . '.svg',
        ]);

        return GuestSession::create([
            'qr_code_id' => $qrCode->id,
            'token' => bin2hex(random_bytes(32)),
            'expires_at' => now()->addHour(),
        ]);
    }

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

    public function test_checkout_split_creates_one_order_per_group_on_the_guests_table(): void
    {
        $guestSession = $this->createGuestSession();
        $product = $this->createProduct('Nasi Goreng', 20000);

        Livewire::test(LandingPage::class, ['session_token' => $guestSession->token])
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->assertSet('orderSubmitted', true)
            ->assertSet('cart', []);

        $andi = Order::where('customer_name', 'Andi')->first();
        $budi = Order::where('customer_name', 'Budi')->first();

        $this->assertNotNull($andi);
        $this->assertNotNull($budi);
        $this->assertSame($guestSession->qrCode->table_id, $andi->table_id);
        $this->assertSame($guestSession->qrCode->table_id, $budi->table_id);
    }

    public function test_checkout_split_does_nothing_when_not_ready(): void
    {
        $guestSession = $this->createGuestSession();
        $product = $this->createProduct('Nasi Goreng', 20000);

        Livewire::test(LandingPage::class, ['session_token' => $guestSession->token])
            ->call('addToCart', $product->id)
            ->call('addSplitGroup', 'Andi') // only 1 group
            ->call('checkoutSplit');

        $this->assertSame(0, Order::count());
    }

    public function test_checkout_split_is_rejected_once_the_session_expires(): void
    {
        $guestSession = $this->createGuestSession();
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(LandingPage::class, ['session_token' => $guestSession->token])
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0)
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id);

        $guestSession->update(['expires_at' => now()->subMinute()]);

        $component->call('checkoutSplit')->assertStatus(410);

        $this->assertSame(0, Order::count());
    }

    public function test_opening_the_payment_modal_in_split_mode_keeps_qris_selected(): void
    {
        $guestSession = $this->createGuestSession();
        $product = $this->createProduct('Nasi Goreng', 20000);

        Livewire::test(LandingPage::class, ['session_token' => $guestSession->token])
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('toggleSplitMode')
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->set('paymentMethod', 'qris')
            ->call('openPaymentModal')
            ->assertSet('showPaymentModal', true)
            ->assertSet('paymentMethod', 'qris');
    }

    public function test_checkout_split_with_qris_records_per_group_order_details(): void
    {
        $guestSession = $this->createGuestSession();
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(LandingPage::class, ['session_token' => $guestSession->token])
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('toggleSplitMode')
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->set('paymentMethod', 'qris')
            ->call('checkoutSplit')
            ->assertSet('orderSubmitted', true)
            ->assertSet('activeSuccessTabIndex', 0);

        $andi = Order::where('customer_name', 'Andi')->first();
        $budi = Order::where('customer_name', 'Budi')->first();

        $details = $component->instance()->splitOrderDetails;

        $this->assertCount(2, $details);
        $this->assertSame('Andi', $details[0]['name']);
        $this->assertSame($andi->order_number, $details[0]['order_number']);
        $this->assertSame((int) round($andi->total_amount), $details[0]['total']);
        $this->assertSame('Budi', $details[1]['name']);
        $this->assertSame($budi->order_number, $details[1]['order_number']);
        $this->assertSame((int) round($budi->total_amount), $details[1]['total']);
    }

    public function test_switch_success_tab_changes_active_index(): void
    {
        $guestSession = $this->createGuestSession();
        $product = $this->createProduct('Nasi Goreng', 20000);

        Livewire::test(LandingPage::class, ['session_token' => $guestSession->token])
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('toggleSplitMode')
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->set('paymentMethod', 'qris')
            ->call('checkoutSplit')
            ->call('switchSuccessTab', 1)
            ->assertSet('activeSuccessTabIndex', 1)
            ->call('switchSuccessTab', 5) // out of range, no-op
            ->assertSet('activeSuccessTabIndex', 1);
    }
}
