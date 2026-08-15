<?php

use App\Filament\CustomPages\PosCashier;
use App\Livewire\Auth\Login;
use App\Livewire\LandingPage\LandingPage;
use App\Livewire\Pos\Receipt;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Root — redirect to login
// ---------------------------------------------------------------------------
Route::get('/', fn () => redirect()->route('login'));

// ---------------------------------------------------------------------------
// Auth
// ---------------------------------------------------------------------------
Route::get('/login', Login::class)->name('login')->middleware('guest');

// ---------------------------------------------------------------------------
// Cashier (requires authenticated session)
// ---------------------------------------------------------------------------
Route::middleware('auth')->prefix('cashier')->name('cashier.')->group(function () {
    Route::get('/receipt/{orderId}', Receipt::class)->name('receipt');
});

// ---------------------------------------------------------------------------
// POS working page — a Filament page (sidebar/topbar chrome from the "admin"
// panel), but registered here instead of via the panel's own page discovery
// so its URL is /cashier instead of /admin/cashier. Middleware/name-prefixing
// mirrors exactly what Filament's own route registration does for panel
// pages (vendor/filament/filament/routes/web.php), just without the panel's
// path() prefix group.
// ---------------------------------------------------------------------------
$adminPanel = Filament::getPanel('admin');

Route::name('filament.')->group(function () use ($adminPanel) {
    Route::name("{$adminPanel->getId()}.")
        ->middleware($adminPanel->getMiddleware())
        ->group(function () use ($adminPanel) {
            Route::middleware($adminPanel->getAuthMiddleware())
                ->group(fn () => PosCashier::registerRoutes($adminPanel));
        });
});

// ---------------------------------------------------------------------------
// Customer Self-Order — two entry points:
//
//   1. Direct Livewire route at /order (legacy, no table context)
//   2. QR-code route at /order/{table_token} — validated by OrderController
//      Uses qr_token (not table PK) in the URL for security.
// ---------------------------------------------------------------------------
#Public route for customer self-ordering (legacy, no table context)
Route::get('/order/{table_token}', LandingPage::class)->name('order');
Route::get('/{token}', Receipt::class)->name('receipt.show');
