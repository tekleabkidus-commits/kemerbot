<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\BroadcastStatus;
use App\Models\Admin;
use App\Models\Broadcast;
use App\Policies\Concerns\HandlesContentAuthorization;

class BroadcastPolicy
{
    use HandlesContentAuthorization { update as private updateContent;
        delete as private deleteContent; }

    public function update(Admin $admin, Broadcast $record): bool
    {
        return $this->updateContent($admin) && in_array($record->status, [BroadcastStatus::Draft, BroadcastStatus::Scheduled], true);
    }

    public function delete(Admin $admin, Broadcast $record): bool
    {
        return ! $admin->isViewer() && $record->status === BroadcastStatus::Draft;
    }

    /** Lifecycle actions: send now, schedule, cancel, test send, duplicate. */
    public function send(Admin $admin): bool
    {
        return ! $admin->isViewer();
    }
}
