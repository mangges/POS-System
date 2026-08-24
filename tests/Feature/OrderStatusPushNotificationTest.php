<?php

namespace Tests\Feature;

use App\Enum\Orders\OrderStatus;
use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Models\Table;
use App\Notifications\OrderStatusPushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_web_push_reuses_event_message(): void
    {
        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $order = Order::create([
            'order_number' => 'ORD200001',
            'table_id' => $table->id,
            'total_amount' => 10000,
            'status' => OrderStatus::Ready,
        ]);

        $event = new OrderStatusUpdated($order);
        $notification = new OrderStatusPushNotification($event);

        $payload = $notification->toWebPush(null, $notification)->toArray();

        $this->assertSame('Status pesanan', $payload['title']);
        $this->assertSame($event->message, $payload['body']);
        $this->assertSame(['order_id' => $order->id], $payload['data']);
    }
}
