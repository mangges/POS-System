<?php

namespace Tests\Feature;

use App\Enum\Orders\OrderStatus;
use App\Events\OrderStatusUpdated;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_change_broadcasts_event_with_correct_type(): void
    {
        Event::fake([OrderStatusUpdated::class]);

        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $order = Order::create([
            'order_number' => 'ORD000001',
            'table_id' => $table->id,
            'total_amount' => 10000,
            'status' => OrderStatus::Pending,
        ]);

        $order->update(['status' => OrderStatus::Completed]);

        Event::assertDispatched(OrderStatusUpdated::class, function ($event) {
            return $event->type === 'success' && str_contains($event->message, 'selesai');
        });
    }

    public function test_creating_order_does_not_broadcast_status_event(): void
    {
        Event::fake([OrderStatusUpdated::class]);

        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        Order::create([
            'order_number' => 'ORD000002',
            'table_id' => $table->id,
            'total_amount' => 10000,
            'status' => OrderStatus::Pending,
        ]);

        Event::assertNotDispatched(OrderStatusUpdated::class);
    }

    public function test_creating_order_notifies_admins_in_database(): void
    {
        $admin = User::factory()->create();

        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        Order::create([
            'order_number' => 'ORD000003',
            'table_id' => $table->id,
            'total_amount' => 10000,
            'status' => OrderStatus::Pending,
        ]);

        $this->assertSame(1, $admin->fresh()->notifications()->count());
    }

    public function test_accept_order_sets_processing_and_broadcasts(): void
    {
        Event::fake([OrderStatusUpdated::class]);

        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $order = Order::create([
            'order_number' => 'ORD000004',
            'table_id' => $table->id,
            'total_amount' => 10000,
            'status' => OrderStatus::Pending,
        ]);

        app(OrderService::class)->acceptOrder($order->id);

        $this->assertSame(OrderStatus::Processing, $order->fresh()->status);

        Event::assertDispatched(OrderStatusUpdated::class, function ($event) {
            return $event->type === 'info' && str_contains($event->message, 'disiapkan');
        });
    }

    public function test_mark_ready_broadcasts_ready_event(): void
    {
        Event::fake([OrderStatusUpdated::class]);

        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $order = Order::create([
            'order_number' => 'ORD000005',
            'table_id' => $table->id,
            'total_amount' => 10000,
            'status' => OrderStatus::Processing,
        ]);

        app(OrderService::class)->markReady($order->id);

        $this->assertSame(OrderStatus::Ready, $order->fresh()->status);

        Event::assertDispatched(OrderStatusUpdated::class, function ($event) {
            return $event->type === 'success' && str_contains($event->message, 'siap diantar');
        });
    }

    public function test_process_order_update_preserves_table_and_status(): void
    {
        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $category = Category::create(['name' => 'Food', 'slug' => 'food']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Nasi Goreng', 'price' => 15000]);

        $order = Order::create([
            'order_number' => 'ORD000006',
            'table_id' => $table->id,
            'total_amount' => 15000,
            'status' => OrderStatus::Pending,
        ]);

        app(OrderService::class)->acceptOrder($order->id);

        // Cashier checkout on an accepted table order passes tableId=null —
        // this used to null out table_id and reset status back to Pending.
        app(OrderService::class)->processOrder(
            [['id' => $product->id, 'qty' => 1, 'price' => 15000]],
            null,
            'Budi',
            'dine-in',
            $order->id
        );

        $fresh = $order->fresh();
        $this->assertSame($table->id, $fresh->table_id);
        $this->assertSame(OrderStatus::Processing, $fresh->status);
    }
}
