<?php

declare(strict_types=1);

namespace App\Enums;

enum BotLanguage: string
{
    case En = 'en';
    case Am = 'am';

    public const DEFAULT = self::En;

    public function label(): string
    {
        return match ($this) {
            self::En => 'English',
            self::Am => 'Amharic',
        };
    }
}
