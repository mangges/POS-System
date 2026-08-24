<?php

namespace Tests\Feature;

use App\Enum\Orders\OrderStatus;
use App\Models\GuestSession;
use App\Models\Order;
use App\Models\QrCode;
use App\Models\Table;
use App\Notifications\OrderStatusPushNotification;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderStatusPushDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function createGuestSession(Table $table): GuestSession
    {
        $qrCode = QrCode::create(['table_id' => $table->id, 'qr_url' => 'https://example.test', 'file_path' => 'qrcodes/x.svg']);

        return GuestSession::create([
            'token' => bin2hex(random_bytes(32)),
            'qr_code_id' => $qrCode->id,
            'expires_at' => now()->addHour(),
        ]);
    }

    public function test_status_change_sends_push_to_owning_guest_session_only(): void
    {
        Notification::fake();

        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $qrCode = QrCode::create(['table_id' => $table->id, 'qr_url' => 'https://example.test', 'file_path' => 'qrcodes/x.svg']);
        $guestSession = GuestSession::create(['token' => bin2hex(random_bytes(32)), 'qr_code_id' => $qrCode->id, 'expires_at' => now()->addHour()]);
        $otherGuestSession = GuestSession::create(['token' => bin2hex(random_bytes(32)), 'qr_code_id' => $qrCode->id, 'expires_at' => now()->addHour()]);

        $order = app(OrderService::class)->processOrder(
            [], $table->id, 'Budi', 'dine-in', null, 'cash', guestSessionId: $guestSession->id
        );

        $order->update(['status' => OrderStatus::Ready]);

        Notification::assertSentTo($guestSession, OrderStatusPushNotification::class);
        Notification::assertNotSentTo($otherGuestSession, OrderStatusPushNotification::class);
    }

    public function test_status_change_without_guest_session_does_not_error(): void
    {
        Notification::fake();

        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $order = Order::create([
            'order_number' => 'ORD300002',
            'table_id' => $table->id,
            'total_amount' => 10000,
            'status' => OrderStatus::Pending,
        ]);

        $order->update(['status' => OrderStatus::Ready]);

        Notification::assertNothingSent();
    }

    public function test_expired_guest_session_is_extended_on_status_change(): void
    {
        Notification::fake();

        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $guestSession = $this->createGuestSession($table);

        $order = app(OrderService::class)->processOrder(
            [], $table->id, 'Budi', 'dine-in', null, 'cash', guestSessionId: $guestSession->id
        );

        $guestSession->update(['expires_at' => now()->subMinute()]);

        $order->update(['status' => OrderStatus::Ready]);

        $this->assertTrue($guestSession->fresh()->expires_at->isFuture());
    }
}
