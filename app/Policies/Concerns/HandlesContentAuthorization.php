<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\Admin;

/**
 * Shared rules for marketing-content resources (spec §5.9): every role may
 * look, Owner + Marketer may change, Viewer is strictly read-only. Enforced
 * server-side by policies — never only hidden Filament buttons.
 */
trait HandlesContentAuthorization
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
        return ! $admin->isViewer();
    }

    public function update(Admin $admin): bool
    {
        return ! $admin->isViewer();
    }

    public function delete(Admin $admin): bool
    {
        return ! $admin->isViewer();
    }
}
