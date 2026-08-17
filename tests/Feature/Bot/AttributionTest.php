<?php

declare(strict_types=1);

use App\Models\TrackingLink;
use App\Models\User;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
});

it('attributes a valid source code and counts the join on the tracking link', function () {
    $link = TrackingLink::factory()->create(['code' => 'summer24']);

    postWebhook(telegramMessageUpdate(3001, '/start summer24'));

    expect(User::query()->where('tg_chat_id', 3001)->first()->source)->toBe('summer24')
        ->and($link->refresh()->joins_count)->toBe(1);
});

it('stores an unknown-but-sane source code without any tracking link increment', function () {
    postWebhook(telegramMessageUpdate(3001, '/start mystery_code'));

    expect(User::query()->where('tg_chat_id', 3001)->first()->source)->toBe('mystery_code')
        ->and(TrackingLink::query()->count())->toBe(0);
});

it('ignores malformed payloads silently', function () {
    postWebhook(telegramMessageUpdate(3001, '/start <bad payload!>'));

    $user = User::query()->where('tg_chat_id', 3001)->first();

    expect($user)->not->toBeNull()
        ->and($user->source)->toBeNull()
        ->and(fakeTelegram()->sentTo(3001))->toHaveCount(1);
});

it('never rewrites source: first touch is immutable', function () {
    $first = TrackingLink::factory()->create(['code' => 'first']);
    $second = TrackingLink::factory()->create(['code' => 'second']);

    postWebhook(telegramMessageUpdate(3001, '/start first'));
    postWebhook(telegramMessageUpdate(3001, '/start second'));

    expect(User::query()->where('tg_chat_id', 3001)->first()->source)->toBe('first')
        ->and($first->refresh()->joins_count)->toBe(1)
        ->and($second->refresh()->joins_count)->toBe(0);
});

it('does not double-count joins on repeated /start with the same code', function () {
    $link = TrackingLink::factory()->create(['code' => 'summer24']);

    postWebhook(telegramMessageUpdate(3001, '/start summer24'));
    postWebhook(telegramMessageUpdate(3001, '/start summer24'));

    expect($link->refresh()->joins_count)->toBe(1);
});

it('attributes a referral to an existing different user', function () {
    $referrer = User::factory()->create();

    postWebhook(telegramMessageUpdate(3001, "/start ref_{$referrer->id}"));

    expect(User::query()->where('tg_chat_id', 3001)->first()->referred_by_user_id)->toBe($referrer->id);
});

it('blocks self-referral', function () {
    postWebhook(telegramMessageUpdate(3001, '/start'));
    $user = User::query()->where('tg_chat_id', 3001)->first();

    postWebhook(telegramMessageUpdate(3001, "/start ref_{$user->id}"));

    expect($user->refresh()->referred_by_user_id)->toBeNull();
});

it('ignores referrals to unknown users silently', function () {
    postWebhook(telegramMessageUpdate(3001, '/start ref_999999'));

    $user = User::query()->where('tg_chat_id', 3001)->first();

    expect($user->referred_by_user_id)->toBeNull()
        ->and(fakeTelegram()->sentTo(3001))->toHaveCount(1);
});

it('keeps the first referrer forever', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();

    postWebhook(telegramMessageUpdate(3001, "/start ref_{$first->id}"));
    postWebhook(telegramMessageUpdate(3001, "/start ref_{$second->id}"));

    $user = User::query()->where('tg_chat_id', 3001)->first();

    expect($user->referred_by_user_id)->toBe($first->id)
        ->and($first->referrals()->count())->toBe(1)
        ->and($second->referrals()->count())->toBe(0);
});

it('counts each referred user exactly once despite repeated /start', function () {
    $referrer = User::factory()->create();

    postWebhook(telegramMessageUpdate(3001, "/start ref_{$referrer->id}"));
    postWebhook(telegramMessageUpdate(3001, "/start ref_{$referrer->id}"));
    postWebhook(telegramMessageUpdate(3002, "/start ref_{$referrer->id}"));

    expect($referrer->referrals()->count())->toBe(2);
});
