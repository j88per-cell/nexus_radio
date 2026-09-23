<?php

use App\Http\Controllers\RadioController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Frontend polling
Route::get('/now-playing', [RadioController::class, 'nowPlaying']);

// Liquidsoap callbacks (internal, no auth)
Route::get('/radio/next', [RadioController::class, 'next']);
Route::post('/webhook/track-started', [WebhookController::class, 'trackStarted']);
Route::post('/webhook/radio-health', [WebhookController::class, 'radioHealth']);
