<?php

declare(strict_types=1);

namespace App\Enums;

enum KeywordMatchType: string
{
    case Exact = 'exact';
    case Contains = 'contains';
}
