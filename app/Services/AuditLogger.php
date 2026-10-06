<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes audit rows for admin actions (spec §5.9): actor, action, subject,
 * timestamp, SAFE metadata only — never secrets, tokens, or passwords.
 * System-context work (queue jobs, scheduler) has no acting admin and is
 * deliberately not audited here; it is observable via logs instead.
 */
final class AuditLogger
{
    private const FORBIDDEN_META_KEYS = ['password', 'token', 'secret', 'remember_token'];

    public function log(string $action, ?Model $subject = null, array $meta = [], ?Admin $actor = null): void
    {
        $admin = $actor ?? auth()->user();

        if (! $admin instanceof Admin) {
            return;
        }

        AuditLog::query()->create([
            'admin_id' => $admin->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'meta' => $this->sanitize($meta),
            'created_at' => now(),
        ]);
    }

    private function sanitize(array $meta): ?array
    {
        $clean = [];

        foreach ($meta as $key => $value) {
            foreach (self::FORBIDDEN_META_KEYS as $forbidden) {
                if (str_contains(strtolower((string) $key), $forbidden)) {
                    continue 2;
                }
            }

            $clean[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $clean === [] ? null : $clean;
    }
}
