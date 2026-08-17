<?php

declare(strict_types=1);

namespace App\Services\Broadcasts;

use App\Enums\BroadcastStatus;
use RuntimeException;

final class InvalidBroadcastTransition extends RuntimeException
{
    public static function between(BroadcastStatus $from, BroadcastStatus $to): self
    {
        return new self("Invalid broadcast transition: {$from->value} → {$to->value}");
    }

    public static function alreadyStarted(int $broadcastId): self
    {
        return new self("Broadcast {$broadcastId} was already started (double-send prevented)");
    }
}
