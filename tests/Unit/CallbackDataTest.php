<?php

declare(strict_types=1);

use App\Telegram\CallbackActionType;
use App\Telegram\CallbackData;

it('parses the four protocol shapes', function () {
    expect(CallbackData::parse('menu:7')->type)->toBe(CallbackActionType::Menu)
        ->and(CallbackData::parse('menu:7')->menuItemId)->toBe(7)
        ->and(CallbackData::parse('join:check')->type)->toBe(CallbackActionType::JoinCheck)
        ->and(CallbackData::parse('bc:3:9')->type)->toBe(CallbackActionType::BroadcastButton)
        ->and(CallbackData::parse('bc:3:9')->broadcastId)->toBe(3)
        ->and(CallbackData::parse('bc:3:9')->buttonId)->toBe(9)
        ->and(CallbackData::parse('invite:show')->type)->toBe(CallbackActionType::InviteShow);
});

it('rejects everything else', function (?string $data) {
    expect(CallbackData::parse($data))->toBeNull();
})->with([
    null,
    '',
    'menu:',
    'menu:abc',
    'menu:-1',
    'menu:1:2',
    'bc:1',
    'bc:1:2:3',
    'drop table users',
    'join:check2',
    'menu:99999999999999999999',
    str_repeat('a', 65),
]);
