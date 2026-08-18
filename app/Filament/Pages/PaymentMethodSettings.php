<?php

namespace App\Filament\Pages;

use App\Enum\Payments\PaymentMethod;
use App\Enum\Payments\QrisMode;
use App\Models\PaymentMethodSetting;
use App\Services\Qris\QrisConverter;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Zxing\QrReader;

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
            'qris_static_image' => $settings->get(PaymentMethod::Qris->value)?->qris_static_image_path,
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
                        FileUpload::make('qris_static_image')
                            ->label('Gambar QRIS Statis')
                            ->image()
                            ->disk('public')
                            ->directory('payment-methods/qris')
                            ->required(fn (Get $get) => $get('qris_active') && $get('qris_mode') === QrisMode::Dynamic->value)
                            ->visible(fn (Get $get) => $get('qris_active') && $get('qris_mode') === QrisMode::Dynamic->value)
                            ->helperText('Upload foto/scan QRIS statis asli dari penyedia QRIS Anda.')
                            ->rule(function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    if (! $value instanceof TemporaryUploadedFile) {
                                        return;
                                    }

                                    $text = (new QrReader($value->getRealPath()))->text();

                                    if (! is_string($text) || $text === '') {
                                        $fail('Gambar tidak mengandung QR code yang bisa dibaca.');

                                        return;
                                    }

                                    try {
                                        QrisConverter::toDynamic($text, 1);
                                    } catch (\InvalidArgumentException $e) {
                                        $fail('QR terbaca tapi bukan QRIS yang valid: '.$e->getMessage());
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
        $qrisImagePath = $qrisMode === QrisMode::Dynamic->value ? ($data['qris_static_image'] ?? null) : null;
        $qrisStaticString = null;

        if ($qrisImagePath) {
            $text = (new QrReader(Storage::disk('public')->path($qrisImagePath)))->text();
            $qrisStaticString = is_string($text) ? $text : null;
        }

        PaymentMethodSetting::updateOrCreate(
            ['method' => PaymentMethod::Qris->value],
            [
                'is_active' => $data['qris_active'],
                'qris_mode' => $qrisMode,
                'qris_static_image_path' => $qrisImagePath,
                'qris_static_string' => $qrisStaticString,
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
