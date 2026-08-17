<?php

declare(strict_types=1);

use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
});

it('renders the invite menu action as a callback button', function () {
    $item = MenuItem::factory()->create(['action_type' => 'invite']);
    MenuItemTranslation::factory()->for($item)->create(['label' => 'Invite friends']);

    postWebhook(telegramMessageUpdate(12001, '/start'));

    $keyboard = fakeTelegram()->lastSentTo(12001)['params']['reply_markup'];

    expect($keyboard['inline_keyboard'][0][0])->toMatchArray([
        'text' => 'Invite friends',
        'callback_data' => 'invite:show',
    ]);
});

it('shows the personal referral link with per-language share text', function () {
    postWebhook(telegramMessageUpdate(12001, '/start'));
    $user = User::query()->where('tg_chat_id', 12001)->first();

    postWebhook(telegramCallbackUpdate(12001, 'invite:show'));

    $sent = fakeTelegram()->lastSentTo(12001);

    expect($sent['params']['text'])->toContain("https://t.me/SunBetTestBot?start=ref_{$user->id}")
        ->and($sent['params']['text'])->toContain('Invite your friends')
        ->and(fakeTelegram()->callsTo('answerCallbackQuery'))->toHaveCount(1);
});

it('shares amharic text for amharic users', function () {
    postWebhook(telegramCallbackUpdate(12001, 'invite:show', ['language_code' => 'am']));

    expect(fakeTelegram()->lastSentTo(12001)['params']['text'])->toContain('ጓደኞችዎን');
});

it('declines gracefully when referrals are disabled', function () {
    app(SettingsService::class)->set('features', ['referrals' => false]);

    postWebhook(telegramCallbackUpdate(12001, 'invite:show'));

    expect(fakeTelegram()->nothingSent())->toBeTrue()
        ->and(fakeTelegram()->callsTo('answerCallbackQuery'))->toHaveCount(1);
});

it('closes the loop: the shared link attributes the referred friend', function () {
    postWebhook(telegramCallbackUpdate(12001, 'invite:show'));
    $referrer = User::query()->where('tg_chat_id', 12001)->first();

    postWebhook(telegramMessageUpdate(12002, "/start ref_{$referrer->id}"));

    expect(User::query()->where('tg_chat_id', 12002)->first()->referred_by_user_id)
        ->toBe($referrer->id)
        ->and($referrer->referrals()->count())->toBe(1);
});
