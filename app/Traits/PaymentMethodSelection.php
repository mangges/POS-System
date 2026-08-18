<?php

namespace App\Traits;

use App\Enum\Payments\QrisMode;
use App\Models\PaymentMethodSetting;
use App\Services\Qris\QrisConverter;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

trait PaymentMethodSelection
{
    #[Computed]
    public function activeMethods(): array
    {
        return PaymentMethodSetting::where('is_active', true)->orderBy('id')->get()->map(fn ($setting) => $setting->method->value)->all();
    }

    #[Computed]
    public function qrisImage(): ?string
    {
        return $this->qrisImageForAmount($this->total);
    }

    public function qrisImageForAmount(int|float|null $amount): ?string
    {
        if ($this->paymentMethod !== 'qris' || $amount === null) {
            return null;
        }

        $setting = PaymentMethodSetting::forMethod('qris');

        if (! $setting || $setting->qris_mode !== QrisMode::Dynamic || empty($setting->qris_static_string)) {
            return null;
        }

        try {
            $payload = QrisConverter::toDynamic($setting->qris_static_string, (int) round($amount));
        } catch (InvalidArgumentException) {
            return null;
        }

        return QrCode::size(220)->generate($payload);
    }

    public function ensureActivePaymentMethod(): void
    {
        if (! in_array($this->paymentMethod, $this->activeMethods, true)) {
            $this->paymentMethod = $this->activeMethods[0] ?? 'cash';
        }
    }
}
