<?php

namespace App\Filament\CustomPages;

use Filament\Pages\Page;
use Filament\Support\Enums\Width;

/**
 * Same reasoning as PosCashier — kept out of app/Filament/Pages (which is
 * auto-discovered/auto-routed under /admin) so this can keep its existing
 * URL/route name (cashier.receipt, /cashier/receipt/{orderId}) while still
 * rendering inside the Filament admin chrome. Registered manually in
 * routes/web.php.
 */
class PosReceipt extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = '';

    protected string $view = 'filament.pages.pos-receipt';

    protected Width|string|null $maxContentWidth = Width::Full;

    public ?int $orderId = null;

    public function mount(int $orderId): void
    {
        $this->orderId = $orderId;
    }
}
