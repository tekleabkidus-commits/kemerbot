<?php

declare(strict_types=1);

use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
});

function menuItem(array $attrs = [], string $label = 'Item', ?string $replyText = null): MenuItem
{
    $item = MenuItem::factory()->create($attrs);
    MenuItemTranslation::factory()->for($item)->create([
        'label' => $label,
        'reply_text' => $replyText,
    ]);

    return $item;
}

it('sends the translated reply for a reply action', function () {
    $item = menuItem(label: 'About us', replyText: 'SunBet — Ethiopia’s home of sport.');

    postWebhook(telegramCallbackUpdate(6001, "menu:{$item->id}"));

    expect(fakeTelegram()->lastSentTo(6001)['params']['text'])
        ->toBe('SunBet — Ethiopia’s home of sport.')
        ->and(fakeTelegram()->callsTo('answerCallbackQuery'))->toHaveCount(1);
});

it('renders submenu children with a back button', function () {
    $parent = menuItem(['action_type' => 'submenu'], label: 'Games');
    $child = MenuItem::factory()->for($parent, 'parent')->url('https://sunbet.et/games')->create();
    MenuItemTranslation::factory()->for($child)->create(['label' => 'All games']);

    postWebhook(telegramCallbackUpdate(6001, "menu:{$parent->id}"));

    $rows = fakeTelegram()->lastSentTo(6001)['params']['reply_markup']['inline_keyboard'];

    expect($rows[0][0])->toMatchArray(['text' => 'All games', 'url' => 'https://sunbet.et/games'])
        ->and(end($rows)[0]['text'])->toContain('Back')
        ->and(end($rows)[0]['callback_data'])->toBe('menu:0');
});

it('returns to the main menu via menu:0', function () {
    menuItem(label: 'Root A');

    postWebhook(telegramCallbackUpdate(6001, 'menu:0'));

    $sent = fakeTelegram()->lastSentTo(6001);

    expect($sent['params']['text'])->toBe('Choose an option:')
        ->and($sent['params']['reply_markup']['inline_keyboard'][0][0]['text'])->toBe('Root A');
});

it('renders url and webapp items as link buttons, not callbacks', function () {
    menuItem(['action_type' => 'url', 'url' => 'https://sunbet.et', 'position' => 0], label: 'Website');
    menuItem(['action_type' => 'webapp', 'url' => 'https://sunbet.et/app', 'position' => 1], label: 'Mini App');

    postWebhook(telegramCallbackUpdate(6001, 'menu:0'));

    $rows = fakeTelegram()->lastSentTo(6001)['params']['reply_markup']['inline_keyboard'];

    expect($rows[0][0])->toMatchArray(['text' => 'Website', 'url' => 'https://sunbet.et'])
        ->and($rows[1][0])->toMatchArray(['text' => 'Mini App', 'web_app' => ['url' => 'https://sunbet.et/app']]);
});

it('hides inactive items from keyboards', function () {
    menuItem(['position' => 0], label: 'Visible');
    menuItem(['position' => 1, 'is_active' => false], label: 'Hidden');

    postWebhook(telegramCallbackUpdate(6001, 'menu:0'));

    $rows = fakeTelegram()->lastSentTo(6001)['params']['reply_markup']['inline_keyboard'];

    expect($rows)->toHaveCount(1)
        ->and($rows[0][0]['text'])->toBe('Visible');
});

it('answers gracefully when a tapped item is inactive or gone', function () {
    $item = menuItem(['is_active' => false], label: 'Old');

    postWebhook(telegramCallbackUpdate(6001, "menu:{$item->id}"));

    $answer = fakeTelegram()->callsTo('answerCallbackQuery')[0];

    expect($answer['params']['text'])->toContain('no longer available')
        ->and(fakeTelegram()->nothingSent())->toBeTrue();
});

it('always answers hostile callback payloads', function () {
    postWebhook(telegramCallbackUpdate(6001, 'menu:999999; DROP TABLE users'));

    expect(fakeTelegram()->callsTo('answerCallbackQuery'))->toHaveCount(1)
        ->and(fakeTelegram()->nothingSent())->toBeTrue();
});

it('detects circular parenting', function () {
    $a = MenuItem::factory()->submenu()->create();
    $b = MenuItem::factory()->submenu()->for($a, 'parent')->create();
    $c = MenuItem::factory()->submenu()->for($b, 'parent')->create();

    expect($a->wouldCreateCycle($c->id))->toBeTrue()
        ->and($a->wouldCreateCycle($a->id))->toBeTrue()
        ->and($c->wouldCreateCycle($a->id))->toBeFalse()
        ->and($a->wouldCreateCycle(null))->toBeFalse();
});
