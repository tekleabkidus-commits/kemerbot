<?php

declare(strict_types=1);

use App\Models\Broadcast;
use App\Models\BroadcastButton;
use App\Models\BroadcastButtonTranslation;
use App\Models\BroadcastTranslation;
use App\Models\ButtonClick;
use App\Models\User;
use App\Services\Broadcasts\BroadcastTestSender;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
});

it('sends tests to the configured recipients without touching counters', function () {
    app(SettingsService::class)->set('broadcast.test_recipient_chat_ids', [111, 222]);

    $broadcast = Broadcast::factory()->create();
    BroadcastTranslation::factory()->for($broadcast)->create(['text' => 'Hello {first_name}!']);

    $result = app(BroadcastTestSender::class)->send($broadcast->load('translations', 'buttons.translations'));

    $broadcast->refresh();

    expect($result)->toBe(['sent' => 2, 'recipients' => 2])
        ->and(fakeTelegram()->sentTo(111)[0]['params']['text'])->toBe('[TEST] Hello Test!')
        ->and(fakeTelegram()->sentTo(222))->toHaveCount(1)
        ->and($broadcast->queued)->toBe(0)
        ->and($broadcast->sent)->toBe(0)
        ->and($broadcast->failures()->count())->toBe(0);
});

it('reports zero recipients when none are configured', function () {
    $broadcast = Broadcast::factory()->create();
    BroadcastTranslation::factory()->for($broadcast)->create();

    $result = app(BroadcastTestSender::class)->send($broadcast->load('translations', 'buttons.translations'));

    expect($result['recipients'])->toBe(0)
        ->and(fakeTelegram()->nothingSent())->toBeTrue();
});

it('records button clicks from bc: callbacks', function () {
    $broadcast = Broadcast::factory()->create();
    $button = BroadcastButton::factory()->for($broadcast)->callback()->create();
    BroadcastButtonTranslation::factory()->create(['broadcast_button_id' => $button->id]);

    postWebhook(telegramCallbackUpdate(9101, "bc:{$broadcast->id}:{$button->id}"));

    $click = ButtonClick::query()->first();
    $user = User::query()->where('tg_chat_id', 9101)->first();

    expect($click)->not->toBeNull()
        ->and($click->broadcast_id)->toBe($broadcast->id)
        ->and($click->button_id)->toBe($button->id)
        ->and($click->user_id)->toBe($user->id)
        ->and(fakeTelegram()->callsTo('answerCallbackQuery'))->toHaveCount(1);
});

it('counts repeat clicks as separate rows', function () {
    $broadcast = Broadcast::factory()->create();
    $button = BroadcastButton::factory()->for($broadcast)->callback()->create();

    postWebhook(telegramCallbackUpdate(9101, "bc:{$broadcast->id}:{$button->id}"));
    postWebhook(telegramCallbackUpdate(9101, "bc:{$broadcast->id}:{$button->id}"));

    expect(ButtonClick::query()->count())->toBe(2)
        ->and($broadcast->clicks()->count())->toBe(2);
});

it('ignores clicks for unknown broadcasts or foreign buttons but always answers', function () {
    $broadcast = Broadcast::factory()->create();
    $foreign = BroadcastButton::factory()->callback()->create(); // belongs to another broadcast

    postWebhook(telegramCallbackUpdate(9101, 'bc:999999:1'));
    postWebhook(telegramCallbackUpdate(9101, "bc:{$broadcast->id}:{$foreign->id}"));

    expect(ButtonClick::query()->count())->toBe(0)
        ->and(fakeTelegram()->callsTo('answerCallbackQuery'))->toHaveCount(2);
});
