<?php

declare(strict_types=1);

namespace App\Enums;

enum BroadcastType: string
{
    case Standard = 'standard';
    case MatchCard = 'match_card';
    case Poll = 'poll';
}
