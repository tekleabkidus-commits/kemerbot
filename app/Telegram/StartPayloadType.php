<?php

declare(strict_types=1);

namespace App\Telegram;

enum StartPayloadType: string
{
    case None = 'none';
    case Source = 'source';
    case Referral = 'referral';
    case Invalid = 'invalid';
}
