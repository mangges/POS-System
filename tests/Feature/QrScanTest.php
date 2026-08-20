<?php

namespace Tests\Feature;

use App\Models\GuestSession;
use App\Models\QrCode;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrScanTest extends TestCase
{
    use RefreshDatabase;

    private function createTableWithQrCode(): Table
    {
        $table = Table::create(['number' => '1', 'barcode' => 'T1']);
        QrCode::create([
            'table_id' => $table->id,
            'qr_url' => 'https://example.test/order/' . $table->qr_token,
            'file_path' => 'qrcodes/table_1/table_1.svg',
        ]);

        return $table;
    }

    public function test_scanning_qr_creates_a_guest_session_and_redirects_to_menu(): void
    {
        $table = $this->createTableWithQrCode();

        $response = $this->get(route('order', ['table_token' => $table->qr_token]));

        $session = GuestSession::first();

        $this->assertNotNull($session);
        $this->assertSame($table->qrCode->id, $session->qr_code_id);
        $response->assertRedirect(route('menu', ['session_token' => $session->token]));
    }

    public function test_each_scan_of_the_same_qr_produces_a_different_valid_token(): void
    {
        $table = $this->createTableWithQrCode();

        $this->get(route('order', ['table_token' => $table->qr_token]));
        $this->get(route('order', ['table_token' => $table->qr_token]));

        $sessions = GuestSession::all();

        $this->assertCount(2, $sessions);
        $this->assertNotSame($sessions[0]->token, $sessions[1]->token);
        $this->assertFalse($sessions[0]->isExpired());
        $this->assertFalse($sessions[1]->isExpired());
    }

    public function test_scanning_an_unknown_table_token_404s(): void
    {
        $response = $this->get(route('order', ['table_token' => 'does-not-exist']));

        $response->assertNotFound();
    }
}
