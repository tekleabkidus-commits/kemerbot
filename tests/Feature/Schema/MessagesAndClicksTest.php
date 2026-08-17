<?php

declare(strict_types=1);

use App\Enums\MessageDirection;
use App\Models\ButtonClick;
use App\Models\TelegramMessage;
use App\Models\User;

it('captures inbound messages with media metadata', function () {
    $message = TelegramMessage::factory()->create([
        'type' => 'photo',
        'media_meta' => ['file_id' => 'abc', 'width' => 800],
    ]);

    $message->refresh();

    expect($message->direction)->toBe(MessageDirection::Inbound)
        ->and($message->media_meta['width'])->toBe(800)
        ->and($message->admin_id)->toBeNull();
});

it('records admin 1:1 replies with the sending admin', function () {
    $message = TelegramMessage::factory()->adminOutbound()->create();

    expect($message->direction)->toBe(MessageDirection::AdminOutbound)
        ->and($message->admin)->not->toBeNull();
});

it('keeps 1:1 history but nulls admin_id when the admin is deleted', function () {
    $message = TelegramMessage::factory()->adminOutbound()->create();

    $message->admin->delete();

    expect($message->refresh()->admin_id)->toBeNull();
});

it('cascades messages when a user is deleted', function () {
    $user = User::factory()->create();
    TelegramMessage::factory()->for($user)->count(2)->create();

    $user->delete();

    expect(TelegramMessage::query()->count())->toBe(0);
});

it('links button clicks to user, broadcast and button', function () {
    $click = ButtonClick::factory()->create();

    expect($click->user)->not->toBeNull()
        ->and($click->broadcast)->not->toBeNull()
        ->and($click->button->broadcast_id)->toBe($click->broadcast_id)
        ->and($click->clicked_at)->not->toBeNull();
});

it('cascades clicks when the broadcast is deleted', function () {
    $click = ButtonClick::factory()->create();

    $click->broadcast->delete();

    expect(ButtonClick::query()->count())->toBe(0);
});
