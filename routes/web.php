<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Root — redirect to login
// ---------------------------------------------------------------------------
Route::get('/', fn () => redirect()->route('login'));

// ---------------------------------------------------------------------------
// Auth
// ---------------------------------------------------------------------------
Route::get('/login', App\Livewire\Auth\Login::class)->name('login')->middleware('guest');

// ---------------------------------------------------------------------------
// Cashier (requires authenticated session)
// ---------------------------------------------------------------------------
Route::get('/cashier', App\Livewire\Pos\Cashier::class)->middleware('auth');

// ---------------------------------------------------------------------------
// Customer Self-Order — two entry points:
//
//   1. Direct Livewire route at /order (legacy, no table context)
//   2. QR-code route at /order/{table_token} — validated by OrderController
//      Uses qr_token (not table PK) in the URL for security.
// ---------------------------------------------------------------------------
Route::get('/order', App\Livewire\LandingPage\LandingPage::class)->name('order');

Route::get('/order/{table_token}', [OrderController::class, 'menu'])
    ->name('order.menu')
    ->where('table_token', '[A-Za-z0-9]+');
