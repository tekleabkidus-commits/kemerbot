<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;

/**
 * Bot audience records: browsable by every role, never created or edited by
 * hand (the bot owns them). Direct 1:1 messages need write rights (spec §5.7).
 */
class UserPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    public function view(Admin $admin): bool
    {
        return true;
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    public function update(Admin $admin): bool
    {
        return false;
    }

    public function delete(Admin $admin): bool
    {
        return $admin->isOwner();
    }

    public function message(Admin $admin): bool
    {
        return ! $admin->isViewer();
    }
}
