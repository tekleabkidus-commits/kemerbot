<?php

declare(strict_types=1);

use App\Services\Telegram\FakeTelegramClient;
use App\Services\Telegram\TelegramClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Tests never call real Telegram (spec §14). Swap in the fake globally;
        // grab it via fakeTelegram() to configure or inspect.
        $this->app->singleton(TelegramClient::class, fn () => new FakeTelegramClient);

        // Isolated Redis DB (phpunit.xml REDIS_DB=15) for dedupe + rate limiter.
        Redis::connection()->flushdb();
    })
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

function fakeTelegram(): FakeTelegramClient
{
    $client = app(TelegramClient::class);

    assert($client instanceof FakeTelegramClient);

    return $client;
}

/** Build a Telegram update payload for a private-chat text message. */
function telegramMessageUpdate(int $chatId, string $text, array $fromOverrides = [], ?int $updateId = null): array
{
    static $sequence = 1;

    return [
        'update_id' => $updateId ?? $sequence++,
        'message' => [
            'message_id' => 100 + ($updateId ?? $sequence),
            'from' => array_merge([
                'id' => $chatId,
                'is_bot' => false,
                'first_name' => 'Abel',
                'language_code' => 'en',
            ], $fromOverrides),
            'chat' => ['id' => $chatId, 'type' => 'private'],
            'date' => now()->getTimestamp(),
            'text' => $text,
        ],
    ];
}

/** Build a Telegram update payload for a callback query. */
function telegramCallbackUpdate(int $chatId, string $data, array $fromOverrides = [], ?int $updateId = null): array
{
    static $sequence = 500_000;

    return [
        'update_id' => $updateId ?? $sequence++,
        'callback_query' => [
            'id' => 'cbq-'.($updateId ?? $sequence),
            'from' => array_merge([
                'id' => $chatId,
                'is_bot' => false,
                'first_name' => 'Abel',
                'language_code' => 'en',
            ], $fromOverrides),
            'message' => [
                'message_id' => 55,
                'chat' => ['id' => $chatId, 'type' => 'private'],
            ],
            'data' => $data,
        ],
    ];
}

/** POST an update to the webhook with the correct secret header. */
function postWebhook(array $update): TestResponse
{
    return test()->postJson('/telegram/webhook', $update, [
        'X-Telegram-Bot-Api-Secret-Token' => config('telegram.webhook_secret'),
    ]);
}
