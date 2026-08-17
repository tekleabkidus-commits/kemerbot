<?php

declare(strict_types=1);

namespace App\Enums;

enum AutomationUserStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Cooldown = 'cooldown';
}
