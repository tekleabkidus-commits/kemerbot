<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;

/** Admin + role management is Owner-only (spec §5.9). */
class AdminPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->isOwner();
    }

    public function view(Admin $admin): bool
    {
        return $admin->isOwner();
    }

    public function create(Admin $admin): bool
    {
        return $admin->isOwner();
    }

    public function update(Admin $admin): bool
    {
        return $admin->isOwner();
    }

    public function delete(Admin $admin, Admin $subject): bool
    {
        // Owners manage admins, but nobody deletes their own account.
        return $admin->isOwner() && $admin->id !== $subject->id;
    }
}
