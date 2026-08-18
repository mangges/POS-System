<?php

namespace Tests\Feature\Filament;

use App\Enum\Payments\QrisMode;
use App\Filament\Pages\PaymentMethodSettings;
use App\Models\PaymentMethodSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentMethodSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_switch_qris_to_dynamic_mode_and_save(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(PaymentMethodSettings::class)
            ->set('data.qris_active', true)
            ->set('data.qris_mode', 'dynamic')
            ->set('data.qris_static_string', '0002010102112614TESTMERCHANT015204581253033605802ID5909TOKO TEST6007JAKARTA63041066')
            ->call('save');

        $setting = PaymentMethodSetting::forMethod('qris');

        $this->assertTrue($setting->is_active);
        $this->assertSame(QrisMode::Dynamic, $setting->qris_mode);
        $this->assertSame('0002010102112614TESTMERCHANT015204581253033605802ID5909TOKO TEST6007JAKARTA63041066', $setting->qris_static_string);
    }

    public function test_admin_can_deactivate_transfer(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(PaymentMethodSettings::class)
            ->set('data.transfer_active', false)
            ->call('save');

        $this->assertFalse(PaymentMethodSetting::forMethod('transfer')->is_active);
    }
}
