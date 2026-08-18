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

    public function test_admin_can_deactivate_qris_without_throwing(): void
    {
        $this->actingAs(User::factory()->create());

        // Deactivating QRIS hides the Radio/Textarea fields, so Filament's schema
        // dehydration strips `qris_mode`/`qris_static_string` from the form state
        // entirely (they are absent keys, not null). save() must not throw.
        Livewire::test(PaymentMethodSettings::class)
            ->set('data.qris_active', false)
            ->call('save')
            ->assertHasNoErrors();

        $setting = PaymentMethodSetting::forMethod('qris');

        $this->assertNotNull($setting);
        $this->assertFalse($setting->is_active);
        $this->assertSame(QrisMode::Edc, $setting->qris_mode);
        $this->assertNull($setting->qris_static_string);
    }

    public function test_saving_dynamic_qris_with_invalid_static_string_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(PaymentMethodSettings::class)
            ->set('data.qris_active', true)
            ->set('data.qris_mode', 'dynamic')
            ->set('data.qris_static_string', 'not a valid qris string at all')
            ->call('save')
            ->assertHasErrors(['data.qris_static_string']);

        $setting = PaymentMethodSetting::forMethod('qris');

        $this->assertNull($setting->qris_static_string);
    }

    public function test_mount_falls_back_to_defaults_when_qris_row_is_missing(): void
    {
        $this->actingAs(User::factory()->create());

        PaymentMethodSetting::where('method', 'qris')->delete();

        Livewire::test(PaymentMethodSettings::class)
            ->assertSet('data.qris_active', true)
            ->assertSet('data.qris_mode', QrisMode::Edc->value)
            ->assertSet('data.qris_static_string', null);
    }
}
