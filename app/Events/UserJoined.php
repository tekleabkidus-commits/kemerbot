<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once per NEW bot user (spec §5.1) — the automation engine's
 * user_joined trigger enrolls from this. Never fired for repeat /start.
 */
final class UserJoined
{
    use Dispatchable;

    public function __construct(public readonly User $user) {}
}
