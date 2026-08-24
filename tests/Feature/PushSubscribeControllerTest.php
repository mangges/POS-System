<?php

namespace Tests\Feature;

use App\Models\GuestSession;
use App\Models\QrCode;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscribeControllerTest extends TestCase
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

    public function test_it_stores_push_subscription_for_guest_session(): void
    {
        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $guestSession = $this->createGuestSession($table);

        $response = $this->postJson("/menu/{$guestSession->token}/push-subscribe", [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-token'],
        ]);

        $response->assertOk();
        $this->assertSame(1, $guestSession->pushSubscriptions()->count());
        $this->assertSame('https://fcm.googleapis.com/fcm/send/abc123', $guestSession->pushSubscriptions()->first()->endpoint);
    }

    public function test_it_404s_for_unknown_session_token(): void
    {
        $response = $this->postJson('/menu/does-not-exist/push-subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        ]);

        $response->assertNotFound();
    }
}
