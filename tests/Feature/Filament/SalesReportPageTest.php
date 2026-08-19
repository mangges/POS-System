<?php

namespace Tests\Feature\Filament;

use App\Enum\Orders\OrderStatus;
use App\Filament\Pages\Reports\SalesReport;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesReportPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $status, ?string $paymentMethod = null, float $amount = 0): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'total_amount' => $amount,
            'tax' => 0,
            'discount' => 0,
            'status' => $status,
            'order_type' => 'dine_in',
        ]);

        if ($paymentMethod) {
            $payment = Payment::create([
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'status' => 'success',
            ]);

            $order->update(['payment_id' => $payment->id]);
        }

        return $order->fresh();
    }

    public function test_only_completed_orders_are_counted_and_grouped_by_payment_method(): void
    {
        $this->actingAs(User::factory()->create());

        $this->makeOrder(OrderStatus::Completed->value, 'cash', 50000);
        $this->makeOrder(OrderStatus::Completed->value, 'qris', 30000);
        $this->makeOrder(OrderStatus::Pending->value, 'cash', 99999);
        $this->makeOrder(OrderStatus::Cancelled->value, 'cash', 99999);

        $rows = Livewire::test(SalesReport::class)->instance()->getRows();

        $this->assertCount(1, $rows);
        $today = $rows->first();
        $this->assertSame(2, $today['order_count']);
        $this->assertEquals(80000.0, $today['total_revenue']);
        $this->assertEquals(50000.0, $today['cash_revenue']);
        $this->assertEquals(30000.0, $today['qris_revenue']);
        $this->assertEquals(0.0, $today['transfer_revenue']);
    }
}
