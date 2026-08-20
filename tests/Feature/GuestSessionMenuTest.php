<?php

namespace Tests\Feature;

use App\Livewire\LandingPage\LandingPage;
use App\Models\GuestSession;
use App\Models\QrCode;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GuestSessionMenuTest extends TestCase
{
    use RefreshDatabase;

    private function createGuestSession(int $minutesFromNow = 60): GuestSession
    {
        $table = Table::create(['number' => '1', 'barcode' => 'T1']);
        $qrCode = QrCode::create([
            'table_id' => $table->id,
            'qr_url' => 'https://example.test/order/' . $table->qr_token,
            'file_path' => 'qrcodes/table_1/table_1.svg',
        ]);

        return GuestSession::create([
            'token' => bin2hex(random_bytes(32)),
            'qr_code_id' => $qrCode->id,
            'expires_at' => now()->addMinutes($minutesFromNow),
        ]);
    }

    public function test_menu_loads_with_a_valid_session_token(): void
    {
        $session = $this->createGuestSession();

        Livewire::test(LandingPage::class, ['session_token' => $session->token])
            ->assertStatus(200)
            ->assertSet('table.id', $session->qrCode->table_id);
    }

    public function test_menu_rejects_an_unknown_session_token(): void
    {
        $this->get(route('menu', ['session_token' => 'nonexistent']))
            ->assertNotFound();
    }

    public function test_menu_rejects_an_expired_session_token(): void
    {
        $session = $this->createGuestSession(minutesFromNow: -1);

        $this->get(route('menu', ['session_token' => $session->token]))
            ->assertStatus(410);
    }
}
