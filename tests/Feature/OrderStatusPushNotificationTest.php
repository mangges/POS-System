<?php

namespace Tests\Feature;

use App\Enum\Orders\OrderStatus;
use App\Events\OrderStatusUpdated;
use App\Models\GuestSession;
use App\Models\Order;
use App\Models\QrCode;
use App\Models\Table;
use App\Notifications\OrderStatusPushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_web_push_reuses_event_message_and_links_to_the_guest_session_menu(): void
    {
        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $qrCode = QrCode::create(['table_id' => $table->id, 'qr_url' => 'https://example.test', 'file_path' => 'qrcodes/x.svg']);
        $guestSession = GuestSession::create([
            'token' => bin2hex(random_bytes(32)),
            'qr_code_id' => $qrCode->id,
            'expires_at' => now()->addHour(),
        ]);
        $order = Order::create([
            'order_number' => 'ORD200001',
            'table_id' => $table->id,
            'guest_session_id' => $guestSession->id,
            'total_amount' => 10000,
            'status' => OrderStatus::Ready,
        ]);

        $event = new OrderStatusUpdated($order);
        $notification = new OrderStatusPushNotification($event);

        $payload = $notification->toWebPush($guestSession, $notification)->toArray();

        $this->assertSame('Status pesanan', $payload['title']);
        $this->assertSame($event->message, $payload['body']);
        $this->assertSame($order->id, $payload['data']['order_id']);
        $this->assertSame(route('menu', ['session_token' => $guestSession->token]), $payload['data']['url']);
    }
}
