<?php

namespace App\Filament\Pages;

use App\Models\ReceiptSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ReceiptSettings extends Page
{
    protected static ?string $title = 'Struk';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected string $view = 'filament-panels::pages.page';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(ReceiptSetting::current()->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Info Toko')
                    ->schema([
                        TextInput::make('store_name')->label('Nama Toko')->required(),
                        TextInput::make('address')->label('Alamat'),
                        TextInput::make('phone')->label('Telp'),
                        TextInput::make('website')->label('Website'),
                    ]),
                Section::make('Logo')
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label('Logo Toko')
                            ->image()
                            ->disk('public')
                            ->directory('receipts/logo'),
                    ]),
                Section::make('Footer')
                    ->schema([
                        Textarea::make('footer_text')
                            ->label('Teks Footer')
                            ->maxLength(500)
                            ->helperText('Tiap baris baru akan tampil sebagai baris terpisah di struk.'),
                    ]),
                Section::make('QR Code')
                    ->schema([
                        Toggle::make('show_qr')->label('Tampilkan QR e-receipt'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Simpan')
                                ->submit('save'),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $setting = ReceiptSetting::query()->first();

        if ($setting) {
            $setting->update($data);
        } else {
            ReceiptSetting::create($data);
        }

        Notification::make()
            ->title('Pengaturan struk disimpan')
            ->success()
            ->send();
    }
}
