<?php

namespace App\Filament\Pages;

use App\Enum\Payments\PaymentMethod;
use App\Enum\Payments\QrisMode;
use App\Models\PaymentMethodSetting;
use App\Services\Qris\QrisConverter;
use BackedEnum;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PaymentMethodSettings extends Page
{
    protected static ?string $title = 'Payment Method';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected string $view = 'filament.pages.payment-method-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = PaymentMethodSetting::all()->keyBy('method');

        $this->form->fill([
            'cash_active' => $settings->get(PaymentMethod::Cash->value)?->is_active ?? true,
            'qris_active' => $settings->get(PaymentMethod::Qris->value)?->is_active ?? true,
            'qris_mode' => $settings->get(PaymentMethod::Qris->value)?->qris_mode?->value ?? QrisMode::Edc->value,
            'qris_static_string' => $settings->get(PaymentMethod::Qris->value)?->qris_static_string,
            'transfer_active' => $settings->get(PaymentMethod::Transfer->value)?->is_active ?? true,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Cash')
                    ->schema([
                        Toggle::make('cash_active')->label('Aktif'),
                    ]),
                Section::make('QRIS')
                    ->schema([
                        Toggle::make('qris_active')->label('Aktif')->live(),
                        Radio::make('qris_mode')
                            ->label('Mode')
                            ->options([
                                QrisMode::Edc->value => QrisMode::Edc->label(),
                                QrisMode::Dynamic->value => QrisMode::Dynamic->label(),
                            ])
                            ->default(QrisMode::Edc->value)
                            ->live()
                            ->visible(fn (Get $get) => $get('qris_active')),
                        Textarea::make('qris_static_string')
                            ->label('Static QRIS String')
                            ->rows(4)
                            ->required(fn (Get $get) => $get('qris_active') && $get('qris_mode') === QrisMode::Dynamic->value)
                            ->visible(fn (Get $get) => $get('qris_active') && $get('qris_mode') === QrisMode::Dynamic->value)
                            ->rule(function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    if (empty($value)) {
                                        return;
                                    }

                                    try {
                                        QrisConverter::toDynamic($value, 1);
                                    } catch (\InvalidArgumentException $e) {
                                        $fail('String QRIS tidak valid: '.$e->getMessage());
                                    }
                                };
                            }),
                    ]),
                Section::make('Kartu/Debit')
                    ->schema([
                        Toggle::make('transfer_active')->label('Aktif'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        PaymentMethodSetting::updateOrCreate(
            ['method' => PaymentMethod::Cash->value],
            ['is_active' => $data['cash_active']],
        );

        $qrisMode = $data['qris_mode'] ?? QrisMode::Edc->value;

        PaymentMethodSetting::updateOrCreate(
            ['method' => PaymentMethod::Qris->value],
            [
                'is_active' => $data['qris_active'],
                'qris_mode' => $qrisMode,
                'qris_static_string' => $qrisMode === QrisMode::Dynamic->value ? ($data['qris_static_string'] ?? null) : null,
            ],
        );

        PaymentMethodSetting::updateOrCreate(
            ['method' => PaymentMethod::Transfer->value],
            ['is_active' => $data['transfer_active']],
        );

        Notification::make()
            ->title('Payment method settings saved')
            ->success()
            ->send();
    }
}
