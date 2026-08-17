<?php

declare(strict_types=1);

namespace App\Enums;

enum FailureCategory: string
{
    case Blocked = 'blocked';
    case Rate = 'rate';
    case Network = 'network';
    case Invalid = 'invalid';
    case Other = 'other';
}
