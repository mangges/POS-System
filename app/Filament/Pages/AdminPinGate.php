<?php

namespace App\Filament\Pages;

use App\Http\Middleware\EnsureResourcePinUnlocked;
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

    public function mount(?string $resource = null, ?string $redirect = null): void
    {
        $this->resource = $resource ?? request()->query('resource', '');
        $this->redirectUrl = $redirect ?? request()->query('redirect', '');
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
        if (! array_key_exists($this->resource, EnsureResourcePinUnlocked::PROTECTED)) {
            Notification::make()->danger()->title('Resource tidak valid')->send();
            return;
        }

        $admin = User::role('admin')->where('pin', $this->pin)->first();

        if (! $admin) {
            Notification::make()->danger()->title('PIN salah')->send();
            return;
        }

        auth()->user()->syncPermissions(["access-{$this->resource}"]);

        $this->redirect($this->safeRedirectUrl());
    }

    protected function safeRedirectUrl(): string
    {
        $normalized = str_replace('\\', '/', $this->redirectUrl);

        if (str_starts_with($normalized, '/') && ! str_starts_with($normalized, '//')) {
            return $this->redirectUrl;
        }

        return '/admin';
    }
}
