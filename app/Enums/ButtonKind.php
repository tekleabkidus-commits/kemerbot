<?php

declare(strict_types=1);

namespace App\Enums;

enum ButtonKind: string
{
    case Url = 'url';
    case Callback = 'callback';
}
