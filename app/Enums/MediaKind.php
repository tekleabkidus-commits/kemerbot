<?php

declare(strict_types=1);

namespace App\Enums;

enum MediaKind: string
{
    case Photo = 'photo';
    case Video = 'video';
    case Animation = 'animation';
}
