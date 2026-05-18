<?php

use App\Http\Controllers\MagicLoginController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::view('/privacy', 'privacy')->name('privacy');

Route::get('/auth/magic/{token}', MagicLoginController::class)->name('magic.consume');

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('home');
})->name('logout');
