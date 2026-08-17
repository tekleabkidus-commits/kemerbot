<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Bot\BotMessageSender;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
});

it('marks a user blocked when a send returns 403', function () {
    fakeTelegram()->blockNextSend(8001);

    postWebhook(telegramMessageUpdate(8001, '/start'));

    $user = User::query()->where('tg_chat_id', 8001)->first();

    expect($user->blocked_bot)->toBeTrue()
        ->and($user->blocked_at)->not->toBeNull();
});

it('skips sends to blocked users without calling telegram', function () {
    $user = User::factory()->blocked()->create();

    $response = app(BotMessageSender::class)->sendToUser($user, 'hello');

    expect($response->successful())->toBeFalse()
        ->and(fakeTelegram()->nothingSent())->toBeTrue();
});

it('clears the blocked flag on any successful inbound update', function () {
    User::factory()->blocked()->create(['tg_chat_id' => 8001]);

    postWebhook(telegramMessageUpdate(8001, 'hello again'));

    $user = User::query()->where('tg_chat_id', 8001)->first();

    expect($user->blocked_bot)->toBeFalse()
        ->and($user->blocked_at)->toBeNull();
});

it('marks blocked immediately on a my_chat_member kicked update', function () {
    $user = User::factory()->create(['tg_chat_id' => 8001]);

    postWebhook([
        'update_id' => 900001,
        'my_chat_member' => [
            'chat' => ['id' => 8001, 'type' => 'private'],
            'new_chat_member' => ['status' => 'kicked'],
        ],
    ]);

    expect($user->refresh()->blocked_bot)->toBeTrue();
});

it('clears blocked on a my_chat_member member update', function () {
    $user = User::factory()->blocked()->create(['tg_chat_id' => 8001]);

    postWebhook([
        'update_id' => 900002,
        'my_chat_member' => [
            'chat' => ['id' => 8001, 'type' => 'private'],
            'new_chat_member' => ['status' => 'member'],
        ],
    ]);

    expect($user->refresh()->blocked_bot)->toBeFalse();
});
