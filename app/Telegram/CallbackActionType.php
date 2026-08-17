<?php

declare(strict_types=1);

namespace App\Telegram;

enum CallbackActionType: string
{
    case Menu = 'menu';
    case JoinCheck = 'join_check';
    case BroadcastButton = 'broadcast_button';
    case InviteShow = 'invite_show';
}
