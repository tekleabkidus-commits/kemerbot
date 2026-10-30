<?php

declare(strict_types=1);

use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;

beforeEach(fn () => $this->seed(SettingsSeeder::class));

it('sends english welcomes to users regardless of telegram language', function (string $language) {
    postWebhook(telegramMessageUpdate(4001, '/start', ['language_code' => $language]));

    expect(fakeTelegram()->lastSentTo(4001)['params']['text'])->toContain('Welcome to KemerBet');
})->with(['en', 'am', 'ru']);

it('ignores any previously configured amharic default', function () {
    app(SettingsService::class)->set('bot.default_language', 'am');
    postWebhook(telegramMessageUpdate(4001, '/start', ['language_code' => 'am']));

    expect(fakeTelegram()->lastSentTo(4001)['params']['text'])->toContain('Welcome to KemerBet');
});

it('always renders the english menu label for all users', function () {
    $item = MenuItem::factory()->url()->create();
    MenuItemTranslation::factory()->for($item)->create(['label' => 'Promotions']);
    MenuItemTranslation::factory()->for($item)->amharic()->create(['label' => 'ማስተዋወቂያዎች']);

    postWebhook(telegramMessageUpdate(4001, '/start', ['language_code' => 'am']));

    expect(fakeTelegram()->lastSentTo(4001)['params']['reply_markup']['inline_keyboard'][0][0]['text'])
        ->toBe('Promotions');
});

it('does not permit users to switch the bot to amharic', function () {
    postWebhook(telegramMessageUpdate(4001, '/language am', ['language_code' => 'am']));

    expect(fakeTelegram()->lastSentTo(4001)['params']['text'])->toBe('This bot uses English only.');
});
