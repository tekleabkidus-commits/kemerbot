<?php

declare(strict_types=1);

namespace App\Services\Broadcasts;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Translates a broadcast's audience_filter jsonb into an Eloquent query
 * (spec §5.3). Blocked users are ALWAYS excluded. The same code path powers
 * the wizard's live estimated count and the snapshot, so they can't diverge.
 */
final class AudienceQuery
{
    /** @return Builder<User> */
    public function build(?array $filter): Builder
    {
        $query = User::query()->where('blocked_bot', false);

        $filter ??= [];

        if (! empty($filter['joined_after'])) {
            $query->where('joined_at', '>=', $filter['joined_after']);
        }

        if (! empty($filter['joined_before'])) {
            $query->where('joined_at', '<', $filter['joined_before']);
        }

        if (! empty($filter['active_last_days'])) {
            $query->where('last_active_at', '>=', now()->subDays((int) $filter['active_last_days']));
        }

        if (! empty($filter['inactive_days'])) {
            $query->where(function (Builder $q) use ($filter) {
                $q->whereNull('last_active_at')
                    ->orWhere('last_active_at', '<', now()->subDays((int) $filter['inactive_days']));
            });
        }

        if (! empty($filter['language'])) {
            $query->where('language', $filter['language']);
        }

        if (! empty($filter['source'])) {
            $query->where('source', $filter['source']);
        }

        if (array_key_exists('in_channel', $filter) && $filter['in_channel'] !== null) {
            $query->where('in_channel', (bool) $filter['in_channel']);
        }

        return $query;
    }

    public function estimatedCount(?array $filter): int
    {
        return $this->build($filter)->count();
    }

    /** Human-readable audience summary for the wizard + review step. */
    public function describe(?array $filter): string
    {
        $filter ??= [];
        $parts = [];

        if (! empty($filter['joined_after'])) {
            $parts[] = 'joined after '.$filter['joined_after'];
        }
        if (! empty($filter['joined_before'])) {
            $parts[] = 'joined before '.$filter['joined_before'];
        }
        if (! empty($filter['active_last_days'])) {
            $parts[] = "active in the last {$filter['active_last_days']} days";
        }
        if (! empty($filter['inactive_days'])) {
            $parts[] = "inactive for {$filter['inactive_days']}+ days";
        }
        if (! empty($filter['language'])) {
            $parts[] = 'language: '.$filter['language'];
        }
        if (! empty($filter['source'])) {
            $parts[] = 'source: '.$filter['source'];
        }
        if (array_key_exists('in_channel', $filter) && $filter['in_channel'] !== null) {
            $parts[] = $filter['in_channel'] ? 'in the channel' : 'not in the channel';
        }

        $summary = $parts === [] ? 'Everyone' : ucfirst(implode(', ', $parts));

        return $summary.' (blocked users always excluded)';
    }
}
