<?php

namespace Tests\Feature;

use App\Enum\Orders\OrderStatus;
use App\Models\GuestSession;
use App\Models\Order;
use App\Models\QrCode;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderGuestSessionRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_belongs_to_guest_session(): void
    {
        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $qrCode = QrCode::create(['table_id' => $table->id, 'qr_url' => 'https://example.test', 'file_path' => 'qrcodes/x.svg']);
        $guestSession = GuestSession::create([
            'token' => bin2hex(random_bytes(32)),
            'qr_code_id' => $qrCode->id,
            'expires_at' => now()->addHour(),
        ]);

        $order = Order::create([
            'order_number' => 'ORD100001',
            'table_id' => $table->id,
            'guest_session_id' => $guestSession->id,
            'total_amount' => 10000,
            'status' => OrderStatus::Pending,
        ]);

        $this->assertTrue($order->fresh()->guestSession->is($guestSession));
    }

    public function test_order_guest_session_is_null_by_default(): void
    {
        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $order = Order::create([
            'order_number' => 'ORD100002',
            'table_id' => $table->id,
            'total_amount' => 10000,
            'status' => OrderStatus::Pending,
        ]);

        $this->assertNull($order->fresh()->guestSession);
    }
}
