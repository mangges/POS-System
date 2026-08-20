<?php

namespace Tests\Feature;

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

class GuestSessionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function createGuestSession(Table $table, int $minutesFromNow = 60): GuestSession
    {
        $qrCode = QrCode::firstOrCreate(
            ['table_id' => $table->id],
            ['qr_url' => 'https://example.test/order/' . $table->qr_token, 'file_path' => 'qrcodes/x.svg']
        );

        return GuestSession::create([
            'token' => bin2hex(random_bytes(32)),
            'qr_code_id' => $qrCode->id,
            'expires_at' => now()->addMinutes($minutesFromNow),
        ]);
    }

    private function createProduct(): Product
    {
        $category = Category::create(['name' => 'Food', 'slug' => 'food']);

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

    public function test_checkout_is_rejected_once_the_session_expires(): void
    {
        $table = Table::create(['number' => '1', 'barcode' => 'T1']);
        $session = $this->createGuestSession($table, minutesFromNow: 60);
        $product = $this->createProduct();

        $component = Livewire::test(LandingPage::class, ['session_token' => $session->token])
            ->call('addToCart', $product->id)
            ->set('customerName', 'Test Customer');

        // Session was valid at mount, but expires before checkout is called
        // (e.g. guest kept the tab open for over an hour).
        $session->update(['expires_at' => now()->subMinute()]);

        $component->call('checkout')->assertStatus(410);

        $this->assertNull(Order::latest('id')->first());
    }

    public function test_checkout_derives_table_from_the_guest_session_not_the_client(): void
    {
        $tableA = Table::create(['number' => '1', 'barcode' => 'T1']);
        $tableB = Table::create(['number' => '2', 'barcode' => 'T2']);
        $sessionA = $this->createGuestSession($tableA);
        $product = $this->createProduct();

        Livewire::test(LandingPage::class, ['session_token' => $sessionA->token])
            ->call('addToCart', $product->id)
            ->set('customerName', 'Test Customer')
            ->call('checkout');

        $order = Order::latest('id')->first();

        $this->assertNotNull($order);
        $this->assertSame($tableA->id, $order->table_id);
        $this->assertNotSame($tableB->id, $order->table_id);
    }
}
