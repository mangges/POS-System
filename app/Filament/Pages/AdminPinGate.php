<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class AdminPinGate extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.admin-pin-gate';

    public string $resource = '';

    public string $redirectUrl = '';

    public string $pin = '';

    public function mount(string $resource = '', string $redirect = ''): void
    {
        $this->resource = $resource;
        $this->redirectUrl = $redirect;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('pin')
                    ->label('Admin PIN')
                    ->numeric()
                    ->length(6)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $admin = User::role('admin')->where('pin', $this->pin)->first();

        if (! $admin) {
            Notification::make()->danger()->title('PIN salah')->send();
            return;
        }

        auth()->user()->syncPermissions(["access-{$this->resource}"]);

        $this->redirect($this->redirectUrl);
    }
}
