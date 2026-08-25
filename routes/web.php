<?php

use App\Filament\CustomPages\PosCashier;
use App\Filament\CustomPages\PosReceipt;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PushSubscribeController;
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
// Locale switch — stores choice in session, SetLocale middleware picks it up
// ---------------------------------------------------------------------------
Route::get('/locale/{locale}', function (string $locale) {
    if (in_array($locale, ['id', 'en'], true)) {
        session(['locale' => $locale]);
    }

    return back();
})->name('locale.switch');

// ---------------------------------------------------------------------------
// POS pages — Filament pages (sidebar/topbar chrome from the "admin" panel),
// but registered here instead of via the panel's own page discovery so their
// URLs are /cashier and /cashier/receipt/{orderId} instead of living under
// /admin. Middleware mirrors exactly what Filament's own route registration
// does for panel pages (vendor/filament/filament/routes/web.php), just
// without the panel's path() prefix group.
// ---------------------------------------------------------------------------
$adminPanel = Filament::getPanel('admin');

Route::middleware($adminPanel->getMiddleware())
    ->group(function () use ($adminPanel) {
        Route::middleware($adminPanel->getAuthMiddleware())
            ->group(function () use ($adminPanel) {
                Route::name('filament.')->group(function () use ($adminPanel) {
                    Route::name("{$adminPanel->getId()}.")
                        ->group(fn () => PosCashier::registerRoutes($adminPanel));
                });

                Route::get('/cashier/receipt/{orderId}', PosReceipt::class)->name('cashier.receipt');
            });
    });

// ---------------------------------------------------------------------------
// Customer Self-Order:
//
//   1. Physical QR points at /order/{table_token} (never changes). Each hit
//      validates the table token and mints a fresh guest session, valid for
//      1 hour, then redirects to the menu using that session's token.
//   2. /menu/{session_token} renders the actual ordering UI. The session
//      token — not the table token — is what identifies the guest for the
//      rest of their visit (menu browsing + checkout).
// ---------------------------------------------------------------------------
Route::get('/order/{table_token}', [OrderController::class, 'scan'])->name('order');
Route::get('/menu/{session_token}', LandingPage::class)->name('menu');
Route::post('/menu/{session_token}/push-subscribe', PushSubscribeController::class)->name('menu.push-subscribe');
Route::get('/{token}', Receipt::class)->name('receipt.show');
