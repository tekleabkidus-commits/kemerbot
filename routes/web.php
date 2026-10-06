<?php

declare(strict_types=1);

use App\Http\Controllers\ConversionController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\TrackingRedirectController;
use App\Http\Middleware\VerifyTelegramWebhookSecret;
use Illuminate\Support\Facades\Route;

Route::post('/integrations/conversions', ConversionController::class)->middleware('throttle:60,1');

Route::get('/', function () {
    return view('welcome');
});

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->middleware(VerifyTelegramWebhookSecret::class)
    ->name('telegram.webhook');

// Tracking-link click redirect (spec §4.6). The route constraint mirrors
// TrackingLink::CODE_PATTERN so hostile codes 404 at the router.
Route::get('/r/{code}', TrackingRedirectController::class)
    ->where('code', '[A-Za-z0-9_-]{1,64}')
    ->name('tracking.redirect');
