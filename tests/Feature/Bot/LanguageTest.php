<?php

declare(strict_types=1);

use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
});

it('welcomes english users in english', function () {
    postWebhook(telegramMessageUpdate(4001, '/start', ['language_code' => 'en']));

    expect(fakeTelegram()->lastSentTo(4001)['params']['text'])->toContain('Welcome to KemerBet');
});

it('welcomes amharic users in amharic', function () {
    postWebhook(telegramMessageUpdate(4001, '/start', ['language_code' => 'am']));

    expect(fakeTelegram()->lastSentTo(4001)['params']['text'])->toContain('እንኳን ወደ KemerBet');
});

it('falls back to the default language for unknown telegram languages', function () {
    postWebhook(telegramMessageUpdate(4001, '/start', ['language_code' => 'ru']));

    expect(fakeTelegram()->lastSentTo(4001)['params']['text'])->toContain('Welcome to KemerBet');
});

it('honors an amharic default language setting', function () {
    app(SettingsService::class)->set('bot.default_language', 'am');

    postWebhook(telegramMessageUpdate(4001, '/start', ['language_code' => 'ru']));

    expect(fakeTelegram()->lastSentTo(4001)['params']['text'])->toContain('እንኳን ወደ KemerBet');
});

it('renders menu labels in the user language', function () {
    $item = MenuItem::factory()->url()->create();
    MenuItemTranslation::factory()->for($item)->create(['label' => 'Promotions']);
    MenuItemTranslation::factory()->for($item)->amharic()->create(['label' => 'ማስተዋወቂያዎች']);

    postWebhook(telegramMessageUpdate(4001, '/start', ['language_code' => 'am']));

    $keyboard = fakeTelegram()->lastSentTo(4001)['params']['reply_markup'];

    expect($keyboard['inline_keyboard'][0][0]['text'])->toBe('ማስተዋወቂያዎች');
});

it('falls back to english labels when an amharic translation is missing', function () {
    $item = MenuItem::factory()->url()->create();
    MenuItemTranslation::factory()->for($item)->create(['label' => 'Promotions']);

    postWebhook(telegramMessageUpdate(4001, '/start', ['language_code' => 'am']));

    $keyboard = fakeTelegram()->lastSentTo(4001)['params']['reply_markup'];

    expect($keyboard['inline_keyboard'][0][0]['text'])->toBe('Promotions');
});
