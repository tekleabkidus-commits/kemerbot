<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
});

it('rejects webhook calls without the secret header', function () {
    $this->postJson('/telegram/webhook', telegramMessageUpdate(1001, '/start'))
        ->assertForbidden();

    expect(User::query()->count())->toBe(0);
});

it('rejects webhook calls with a wrong secret', function () {
    $this->postJson('/telegram/webhook', telegramMessageUpdate(1001, '/start'), [
        'X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret',
    ])->assertForbidden();

    expect(User::query()->count())->toBe(0);
});

it('accepts webhook calls with the correct secret', function () {
    postWebhook(telegramMessageUpdate(1001, '/start'))->assertOk();

    expect(User::query()->where('tg_chat_id', 1001)->exists())->toBeTrue();
});

it('deduplicates updates by update_id', function () {
    $update = telegramMessageUpdate(1001, '/start', updateId: 777);

    postWebhook($update)->assertOk();
    postWebhook($update)->assertOk();

    // Processed once: exactly one welcome send.
    expect(fakeTelegram()->sentTo(1001))->toHaveCount(1)
        ->and(User::query()->count())->toBe(1);
});

it('answers 200 to malformed payloads without crashing', function () {
    postWebhook([])->assertOk();
    postWebhook(['update_id' => 'not-an-int'])->assertOk();
    postWebhook(['update_id' => 12, 'message' => 'not-an-object'])->assertOk();

    expect(User::query()->count())->toBe(0);
});

it('ignores unknown update types gracefully', function () {
    postWebhook(['update_id' => 13, 'shipping_query' => ['id' => 'x']])->assertOk();

    expect(fakeTelegram()->nothingSent())->toBeTrue();
});

it('ignores group-chat messages', function () {
    $update = telegramMessageUpdate(1001, '/start');
    $update['message']['chat'] = ['id' => -100200, 'type' => 'group'];

    postWebhook($update)->assertOk();

    expect(User::query()->count())->toBe(0);
});
