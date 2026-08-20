<?php

namespace Tests\Feature\Models;

use App\Enum\Payments\PaymentMethod;
use App\Enum\Payments\QrisMode;
use App\Models\PaymentMethodSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_seeds_three_methods_active_by_default(): void
    {
        $this->assertSame(3, PaymentMethodSetting::count());
        $this->assertTrue(PaymentMethodSetting::forMethod(PaymentMethod::Cash)->is_active);
        $this->assertTrue(PaymentMethodSetting::forMethod(PaymentMethod::Qris)->is_active);
        $this->assertTrue(PaymentMethodSetting::forMethod(PaymentMethod::Transfer)->is_active);
    }

    public function test_qris_defaults_to_edc_mode(): void
    {
        $this->assertSame(QrisMode::Edc, PaymentMethodSetting::forMethod('qris')->qris_mode);
    }

    public function test_for_method_returns_null_for_unknown_method(): void
    {
        $this->assertNull(PaymentMethodSetting::forMethod('unknown'));
    }
}
