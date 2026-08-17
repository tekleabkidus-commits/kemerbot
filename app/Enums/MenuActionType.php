<?php

declare(strict_types=1);

namespace App\Enums;

enum MenuActionType: string
{
    case Reply = 'reply';
    case Submenu = 'submenu';
    case Url = 'url';
    case Webapp = 'webapp';
    case Invite = 'invite';
}
