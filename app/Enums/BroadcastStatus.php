<?php

declare(strict_types=1);

namespace App\Enums;

enum BroadcastStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Preparing = 'preparing';
    case Sending = 'sending';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    /** Statuses from which no further sending can ever happen. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::Failed], true);
    }
}
