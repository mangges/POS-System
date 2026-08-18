<?php

namespace Tests\Feature\Livewire\Pos;

use App\Livewire\Pos\Cashier;
use App\Models\PaymentMethodSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashierPaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_methods_are_excluded_from_active_methods(): void
    {
        $this->actingAs(User::factory()->create());

        PaymentMethodSetting::where('method', 'transfer')->update(['is_active' => false]);

        Livewire::test(Cashier::class)
            ->assertSet('activeMethods', ['cash', 'qris']);
    }

    public function test_default_payment_method_falls_back_when_cash_is_inactive(): void
    {
        $this->actingAs(User::factory()->create());

        PaymentMethodSetting::where('method', 'cash')->update(['is_active' => false]);

        Livewire::test(Cashier::class)
            ->assertSet('paymentMethod', 'qris');
    }

    public function test_qris_image_is_null_when_mode_is_edc(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Cashier::class)
            ->set('paymentMethod', 'qris')
            ->assertSet('qrisImage', null);
    }

    public function test_qris_image_renders_svg_when_mode_is_dynamic(): void
    {
        $this->actingAs(User::factory()->create());

        PaymentMethodSetting::where('method', 'qris')->update([
            'qris_mode' => 'dynamic',
            'qris_static_string' => '0002010102112614TESTMERCHANT015204581253033605802ID5909TOKO TEST6007JAKARTA63041066',
        ]);

        Livewire::test(Cashier::class)
            ->set('paymentMethod', 'qris')
            ->assertSet('qrisImage', fn (?string $svg) => $svg !== null && str_contains($svg, '<svg'));
    }
}
