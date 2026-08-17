<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
    app(SettingsService::class)->set('channel.id', -1001234567890);
    app(SettingsService::class)->set('channel.url', 'https://t.me/sunbet_channel');
});

it('lets channel members straight through to the welcome', function () {
    fakeTelegram()->setMembership(5001, 'member');

    postWebhook(telegramMessageUpdate(5001, '/start'));

    $user = User::query()->where('tg_chat_id', 5001)->first();

    expect($user->in_channel)->toBeTrue()
        ->and($user->channel_checked_at)->not->toBeNull()
        ->and(fakeTelegram()->lastSentTo(5001)['params']['text'])->toContain('Welcome to SunBet');
});

it('shows non-members the join gate with join and re-check buttons', function () {
    fakeTelegram()->setMembership(5001, 'left');

    postWebhook(telegramMessageUpdate(5001, '/start'));

    $sent = fakeTelegram()->lastSentTo(5001);
    $rows = $sent['params']['reply_markup']['inline_keyboard'];

    expect(User::query()->where('tg_chat_id', 5001)->first()->in_channel)->toBeFalse()
        ->and($sent['params']['text'])->toContain('join our channel')
        ->and($rows[0][0]['url'])->toBe('https://t.me/sunbet_channel')
        ->and($rows[1][0]['callback_data'])->toBe('join:check');
});

it('fails open when telegram errors on the membership check', function () {
    fakeTelegram()->failMembershipChecks();

    postWebhook(telegramMessageUpdate(5001, '/start'));

    // Never checked before + Telegram down → let them in, log a warning.
    expect(fakeTelegram()->lastSentTo(5001)['params']['text'])->toContain('Welcome to SunBet');
});

it('passes the gate after joining via the re-check button', function () {
    fakeTelegram()->setMembership(5001, 'left');
    postWebhook(telegramMessageUpdate(5001, '/start'));

    // User joins the channel, then taps "I've joined ✅".
    fakeTelegram()->setMembership(5001, 'member');
    postWebhook(telegramCallbackUpdate(5001, 'join:check'));

    $user = User::query()->where('tg_chat_id', 5001)->first();

    expect($user->in_channel)->toBeTrue()
        ->and(fakeTelegram()->lastSentTo(5001)['params']['text'])->toContain('Welcome to SunBet')
        ->and(fakeTelegram()->callsTo('answerCallbackQuery'))->toHaveCount(1);
});

it('alerts and keeps the gate when the re-check still fails', function () {
    fakeTelegram()->setMembership(5001, 'left');
    postWebhook(telegramMessageUpdate(5001, '/start'));

    postWebhook(telegramCallbackUpdate(5001, 'join:check'));

    $answer = fakeTelegram()->callsTo('answerCallbackQuery')[0];

    expect($answer['params']['show_alert'])->toBeTrue()
        ->and(User::query()->where('tg_chat_id', 5001)->first()->in_channel)->toBeFalse();
});

it('trusts a fresh cached membership without re-calling telegram', function () {
    User::factory()->inChannel()->create(['tg_chat_id' => 5001]);

    postWebhook(telegramMessageUpdate(5001, '/start'));

    expect(fakeTelegram()->callsTo('getChatMember'))->toHaveCount(0);
});

it('re-checks membership once the cached check is stale', function () {
    User::factory()->create([
        'tg_chat_id' => 5001,
        'in_channel' => true,
        'channel_checked_at' => now()->subMinutes((int) config('telegram.membership_ttl_minutes') + 5),
    ]);

    postWebhook(telegramMessageUpdate(5001, '/start'));

    expect(fakeTelegram()->callsTo('getChatMember'))->toHaveCount(1);
});

it('gates the menus too, not only /start', function () {
    fakeTelegram()->setMembership(5001, 'left');

    postWebhook(telegramCallbackUpdate(5001, 'menu:0'));

    expect(fakeTelegram()->lastSentTo(5001)['params']['text'])->toContain('join our channel');
});
