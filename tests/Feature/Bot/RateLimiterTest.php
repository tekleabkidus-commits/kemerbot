<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Bot\BotMessageSender;
use App\Services\SettingsService;
use App\Services\Telegram\TelegramRateLimiter;
use App\Services\Telegram\TelegramResponse;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('allows exactly the configured rate per second globally', function () {
    Carbon::setTestNow('2026-08-17 12:00:00');
    app(SettingsService::class)->set('telegram.send_rate', 3);

    $limiter = app(TelegramRateLimiter::class);

    expect($limiter->tryAcquire())->toBeTrue()
        ->and($limiter->tryAcquire())->toBeTrue()
        ->and($limiter->tryAcquire())->toBeTrue()
        ->and($limiter->tryAcquire())->toBeFalse();
});

it('frees up in the next one-second window', function () {
    Carbon::setTestNow('2026-08-17 12:00:00');
    app(SettingsService::class)->set('telegram.send_rate', 1);

    $limiter = app(TelegramRateLimiter::class);
    $limiter->tryAcquire();

    expect($limiter->tryAcquire())->toBeFalse();

    Carbon::setTestNow('2026-08-17 12:00:01');

    expect($limiter->tryAcquire())->toBeTrue();
});

it('treats the env rate as a hard ceiling over the DB setting', function () {
    app(SettingsService::class)->set('telegram.send_rate', 9999);

    expect(app(TelegramRateLimiter::class)->rate())->toBe((int) config('telegram.send_rate'));
});

it('pauses the whole bucket when told to', function () {
    Carbon::setTestNow('2026-08-17 12:00:00');
    $limiter = app(TelegramRateLimiter::class);

    $limiter->pause(5);

    expect($limiter->isPaused())->toBeTrue()
        ->and($limiter->tryAcquire())->toBeFalse();

    Carbon::setTestNow('2026-08-17 12:00:06');

    expect($limiter->isPaused())->toBeFalse()
        ->and($limiter->tryAcquire())->toBeTrue();
});

it('honors telegram 429 retry_after by pausing then retrying the send', function () {
    $user = User::factory()->create();

    fakeTelegram()->queueResponse(
        $user->tg_chat_id,
        TelegramResponse::failure(429, 'Too Many Requests: retry after 1', retryAfter: 1),
    );

    $response = app(BotMessageSender::class)->sendToUser($user, 'hello');

    expect($response->successful())->toBeTrue()
        ->and(fakeTelegram()->sentTo($user->tg_chat_id))->toHaveCount(2);
});
