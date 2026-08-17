<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;

/** Polls are authored inside the broadcast wizard; this resource is read-only. */
class PollPolicy
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
        return false;
    }
}
