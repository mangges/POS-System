<?php

namespace Tests\Feature\Livewire\Pos;

use App\Enum\Orders\OrderStatus;
use App\Enum\Orders\PaymentStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_page_renders_payment_status_without_error(): void
    {
        $this->actingAs(User::factory()->create());

        $category = Category::create(['name' => 'Drinks', 'slug' => 'drinks']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Latte',
            'price' => 25000,
            'has_recipe' => false,
            'stock' => 10,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-1',
            'total_amount' => 25000,
            'tax' => 0,
            'discount' => 0,
            'status' => OrderStatus::Completed,
            'order_type' => 'dine_in',
            'token' => 'test-token',
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 25000,
            'status' => PaymentStatus::Success,
        ]);

        $order->update(['payment_id' => $payment->id]);

        $response = $this->get('/cashier/receipt/'.$order->id);

        $response->assertSuccessful();
        $response->assertSee('Success');
        $response->assertSee('#16a34a', false);
    }
}
