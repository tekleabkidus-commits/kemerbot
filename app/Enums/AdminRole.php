<?php

declare(strict_types=1);

namespace App\Enums;

enum AdminRole: string
{
    case Owner = 'owner';
    case Marketer = 'marketer';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Marketer => 'Marketer',
            self::Viewer => 'Viewer',
        };
    }
}
