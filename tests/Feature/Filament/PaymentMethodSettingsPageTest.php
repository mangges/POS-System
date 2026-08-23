<?php

namespace Tests\Feature\Filament;

use App\Enum\Payments\QrisMode;
use App\Filament\Pages\PaymentMethodSettings;
use App\Models\PaymentMethodSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentMethodSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_QRIS_STRING = '0002010102112614TESTMERCHANT015204581253033605802ID5909TOKO TEST6007JAKARTA63041066';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function validQrisImage(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'qris.png',
            file_get_contents(base_path('tests/Fixtures/qris/valid-static-qris.png')),
        );
    }

    public function test_admin_can_switch_qris_to_dynamic_mode_and_save(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        Livewire::test(PaymentMethodSettings::class)
            ->set('data.qris_active', true)
            ->set('data.qris_mode', 'dynamic')
            ->set('data.qris_static_image', $this->validQrisImage())
            ->call('save')
            ->assertHasNoErrors();

        $setting = PaymentMethodSetting::forMethod('qris');

        $this->assertTrue($setting->is_active);
        $this->assertSame(QrisMode::Dynamic, $setting->qris_mode);
        $this->assertSame(self::VALID_QRIS_STRING, $setting->qris_static_string);
        $this->assertNotNull($setting->qris_static_image_path);
        Storage::disk('public')->assertExists($setting->qris_static_image_path);
    }

    public function test_admin_can_deactivate_transfer(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        Livewire::test(PaymentMethodSettings::class)
            ->set('data.transfer_active', false)
            ->call('save');

        $this->assertFalse(PaymentMethodSetting::forMethod('transfer')->is_active);
    }

    public function test_admin_can_deactivate_qris_without_throwing(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        // Deactivating QRIS hides the Radio/FileUpload fields, so Filament's schema
        // dehydration strips `qris_mode`/`qris_static_image` from the form state
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
        $this->assertNull($setting->qris_static_image_path);
    }

    public function test_saving_dynamic_qris_with_an_image_that_has_no_qr_code_is_rejected(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        Livewire::test(PaymentMethodSettings::class)
            ->set('data.qris_active', true)
            ->set('data.qris_mode', 'dynamic')
            ->set('data.qris_static_image', UploadedFile::fake()->image('not-a-qr.png', 200, 200))
            ->call('save')
            ->assertHasErrors(['data.qris_static_image']);

        $setting = PaymentMethodSetting::forMethod('qris');

        $this->assertNull($setting->qris_static_string);
        $this->assertNull($setting->qris_static_image_path);
    }

    public function test_mount_falls_back_to_defaults_when_qris_row_is_missing(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        PaymentMethodSetting::where('method', 'qris')->delete();

        Livewire::test(PaymentMethodSettings::class)
            ->assertSet('data.qris_active', true)
            ->assertSet('data.qris_mode', QrisMode::Edc->value)
            ->assertSet('data.qris_static_image', fn ($value) => empty($value));
    }
}
