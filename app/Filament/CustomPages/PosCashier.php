<?php

namespace App\Filament\CustomPages;

use Filament\Pages\Page;
use Filament\Support\Enums\Width;

/**
 * Deliberately NOT in app/Filament/Pages — that directory is auto-discovered
 * and auto-registered under the panel's /admin prefix. This page is instead
 * routed manually in routes/web.php so its URL is /cashier, not /admin/cashier.
 * Its sidebar entry is added manually too, via ->navigationItems() in
 * AdminPanelProvider.
 */
class PosCashier extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'cashier';
    protected static ?string $title = '';

    protected string $view = 'filament.pages.pos-cashier';

    protected Width|string|null $maxContentWidth = Width::Full;
}
