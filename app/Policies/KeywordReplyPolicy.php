<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Concerns\HandlesContentAuthorization;

class KeywordReplyPolicy
{
    use HandlesContentAuthorization;
}
