<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;

/** Audit log: Owner-only reading; effectively immutable via UI (spec §5.9). */
class AuditLogPolicy
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
