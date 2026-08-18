<?php

namespace Tests\Feature\Livewire\LandingPage;

use App\Livewire\LandingPage\LandingPage;
use App\Models\PaymentMethodSetting;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LandingPagePaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    private function createTable(): Table
    {
        return Table::create(['number' => '1', 'barcode' => 'T1']);
    }

    public function test_inactive_methods_are_excluded_from_active_methods(): void
    {
        $table = $this->createTable();

        PaymentMethodSetting::where('method', 'transfer')->update(['is_active' => false]);

        Livewire::test(LandingPage::class, ['table_token' => $table->qr_token])
            ->assertSet('activeMethods', ['cash', 'qris']);
    }

    public function test_default_payment_method_falls_back_when_cash_is_inactive(): void
    {
        $table = $this->createTable();

        PaymentMethodSetting::where('method', 'cash')->update(['is_active' => false]);

        Livewire::test(LandingPage::class, ['table_token' => $table->qr_token])
            ->assertSet('paymentMethod', 'qris');
    }

    public function test_qris_image_renders_svg_when_mode_is_dynamic(): void
    {
        $table = $this->createTable();

        PaymentMethodSetting::where('method', 'qris')->update([
            'qris_mode' => 'dynamic',
            'qris_static_string' => '0002010102112614TESTMERCHANT015204581253033605802ID5909TOKO TEST6007JAKARTA63041066',
        ]);

        Livewire::test(LandingPage::class, ['table_token' => $table->qr_token])
            ->set('paymentMethod', 'qris')
            ->assertSet('qrisImage', fn (?string $svg) => $svg !== null && str_contains($svg, '<svg'));
    }
}
