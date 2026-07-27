<?php

use App\Livewire\Pos\Cashier;
use App\Livewire\Auth\Login;
use App\Livewire\LandingPage\LandingPage;
use App\Livewire\Pos\Receipt;
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
    Route::get('/', Cashier::class)->name('index');
    Route::get('/receipt/{orderId}', Receipt::class)->name('receipt');
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
