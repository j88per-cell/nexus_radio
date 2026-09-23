<?php

use App\Http\Controllers\MagicLinkController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [MagicLinkController::class, 'show'])->name('login');
    Route::post('login', [MagicLinkController::class, 'send'])->name('magic.send');
});

Route::get('magic/verify', [MagicLinkController::class, 'verify'])
    ->middleware('signed')
    ->name('magic.verify');

Route::post('logout', [MagicLinkController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');
