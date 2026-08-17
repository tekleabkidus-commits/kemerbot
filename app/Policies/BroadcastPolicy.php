<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Policies\Concerns\HandlesContentAuthorization;

class BroadcastPolicy
{
    use HandlesContentAuthorization;

    /** Lifecycle actions: send now, schedule, cancel, test send, duplicate. */
    public function send(Admin $admin): bool
    {
        return ! $admin->isViewer();
    }
}
