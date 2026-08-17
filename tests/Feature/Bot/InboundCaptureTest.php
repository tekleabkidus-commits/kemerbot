<?php

declare(strict_types=1);

use App\Enums\MessageDirection;
use App\Models\TelegramMessage;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
});

it('captures inbound text messages', function () {
    postWebhook(telegramMessageUpdate(9001, 'hello kemerbet'));

    $user = User::query()->where('tg_chat_id', 9001)->first();
    $message = TelegramMessage::query()->where('user_id', $user->id)->first();

    expect($message)->not->toBeNull()
        ->and($message->direction)->toBe(MessageDirection::Inbound)
        ->and($message->type)->toBe('text')
        ->and($message->text)->toBe('hello kemerbet')
        ->and($message->tg_message_id)->not->toBeNull();
});

it('captures /start commands too', function () {
    postWebhook(telegramMessageUpdate(9001, '/start summer24'));

    $user = User::query()->where('tg_chat_id', 9001)->first();

    expect($user->telegramMessages()->count())->toBe(1)
        ->and($user->telegramMessages()->first()->text)->toBe('/start summer24');
});

it('captures photo messages with compact media metadata', function () {
    $update = telegramMessageUpdate(9001, 'x');
    unset($update['message']['text']);
    $update['message']['caption'] = 'look at this';
    $update['message']['photo'] = [
        ['file_id' => 'small', 'width' => 90, 'height' => 90],
        ['file_id' => 'big', 'width' => 800, 'height' => 800],
    ];

    postWebhook($update);

    $message = TelegramMessage::query()->first();

    expect($message->type)->toBe('photo')
        ->and($message->text)->toBe('look at this')
        ->and($message->media_meta['file_id'])->toBe('big')
        ->and($message->media_meta['width'])->toBe(800);
});

it('updates last_active_at on every inbound interaction', function () {
    $user = User::factory()->create([
        'tg_chat_id' => 9001,
        'last_active_at' => Carbon::parse('2026-01-01 00:00:00'),
    ]);

    postWebhook(telegramMessageUpdate(9001, 'ping'));
    $afterMessage = $user->refresh()->last_active_at;

    postWebhook(telegramCallbackUpdate(9001, 'menu:0'));
    $afterCallback = $user->refresh()->last_active_at;

    expect($afterMessage->isAfter('2026-01-01'))->toBeTrue()
        ->and($afterCallback->gte($afterMessage))->toBeTrue();
});

it('records the telegram-reported send time', function () {
    $update = telegramMessageUpdate(9001, 'timed');
    $update['message']['date'] = Carbon::parse('2026-08-15 10:00:00')->getTimestamp();

    postWebhook($update);

    expect(TelegramMessage::query()->first()->sent_at->toDateTimeString())
        ->toBe('2026-08-15 10:00:00');
});
