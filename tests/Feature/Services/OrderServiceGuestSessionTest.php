<?php

namespace Tests\Feature\Services;

use App\Models\GuestSession;
use App\Models\QrCode;
use App\Models\Table;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderServiceGuestSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_order_stores_guest_session_id_on_new_order(): void
    {
        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $qrCode = QrCode::create(['table_id' => $table->id, 'qr_url' => 'https://example.test', 'file_path' => 'qrcodes/x.svg']);
        $guestSession = GuestSession::create([
            'token' => bin2hex(random_bytes(32)),
            'qr_code_id' => $qrCode->id,
            'expires_at' => now()->addHour(),
        ]);

        $order = app(OrderService::class)->processOrder(
            [],
            $table->id,
            'Budi',
            'dine-in',
            null,
            'cash',
            guestSessionId: $guestSession->id,
        );

        $this->assertSame($guestSession->id, $order->fresh()->guest_session_id);
    }

    public function test_process_order_without_guest_session_id_leaves_it_null(): void
    {
        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);

        $order = app(OrderService::class)->processOrder([], $table->id, 'Budi', 'dine-in');

        $this->assertNull($order->fresh()->guest_session_id);
    }
}
