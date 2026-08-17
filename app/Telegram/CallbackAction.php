<?php

declare(strict_types=1);

namespace App\Telegram;

final readonly class CallbackAction
{
    private function __construct(
        public CallbackActionType $type,
        public ?int $menuItemId = null,
        public ?int $broadcastId = null,
        public ?int $buttonId = null,
    ) {}

    public static function menu(int $menuItemId): self
    {
        return new self(CallbackActionType::Menu, menuItemId: $menuItemId);
    }

    public static function joinCheck(): self
    {
        return new self(CallbackActionType::JoinCheck);
    }

    public static function broadcastButton(int $broadcastId, int $buttonId): self
    {
        return new self(CallbackActionType::BroadcastButton, broadcastId: $broadcastId, buttonId: $buttonId);
    }

    public static function inviteShow(): self
    {
        return new self(CallbackActionType::InviteShow);
    }
}
