<?php

use App\Livewire\Auth\Login;
use App\Livewire\Today;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Member web (E1.11) — TALL companion to the Flutter app, on the `web` (Person) guard.
Route::livewire('/login', Login::class)->middleware('guest:web')->name('login');

Route::post('/logout', function () {
    Auth::guard('web')->logout();
    session()->invalidate();
    session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth:web')->name('logout');

Route::livewire('/', Today::class)->middleware('auth:web')->name('today');
