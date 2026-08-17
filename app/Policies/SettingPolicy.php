<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;

/**
 * Settings screen (spec §5.9): Owner sees and edits everything; Marketers may
 * open the page and edit CONTENT settings only (welcome message, default
 * language) — the per-key split is enforced server-side in the Settings page
 * via `updateKey`. Infrastructure keys are Owner-only. Viewers read-only.
 */
class SettingPolicy
{
    /** Keys a Marketer may change: bot content, not infrastructure. */
    public const MARKETER_EDITABLE_KEYS = [
        'welcome.message',
        'welcome.media_file_id',
        'bot.default_language',
    ];

    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    public function view(Admin $admin): bool
    {
        return true;
    }

    public function update(Admin $admin): bool
    {
        return ! $admin->isViewer();
    }

    public function updateKey(Admin $admin, string $key): bool
    {
        if ($admin->isOwner()) {
            return true;
        }

        if ($admin->isViewer()) {
            return false;
        }

        return in_array($key, self::MARKETER_EDITABLE_KEYS, true);
    }
}
