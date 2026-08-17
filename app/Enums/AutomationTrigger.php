<?php

declare(strict_types=1);

namespace App\Enums;

enum AutomationTrigger: string
{
    case UserJoined = 'user_joined';
    case Inactive = 'inactive';
}
