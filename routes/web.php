<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/login', App\Livewire\Auth\Login::class)->name('login')->middleware('guest');

Route::get('/cashier', App\Livewire\Pos\Cashier::class)->middleware('auth');
