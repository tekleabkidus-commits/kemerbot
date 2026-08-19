<?php

declare(strict_types=1);

namespace App\Services\Broadcasts;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Runs every minute from the Laravel scheduler (spec §7): promotes due
 * one-off scheduled broadcasts, and for recurring definitions materializes a
 * one-off child occurrence and advances the parent (spec §4.3).
 *
 * HARDENING §6 concurrency: one-off promotion was always race-safe (the
 * atomic status claim in BroadcastLifecycle::start). Recurring
 * materialization now runs inside a transaction with the parent row locked
 * (SELECT ... FOR UPDATE) and a due re-check, and each occurrence is
 * identified by (parent_broadcast_id, occurrence_at) under a unique index —
 * database-level idempotency even if application locking ever fails.
 */
final class BroadcastScheduler
{
    public function __construct(
        private readonly BroadcastLifecycle $lifecycle,
        private readonly NextOccurrenceCalculator $nextOccurrence,
    ) {}

    /** @return array{started: int, materialized: int} */
    public function processDue(): array
    {
        $started = 0;
        $materialized = 0;

        $due = Broadcast::query()
            ->where('status', BroadcastStatus::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->get();

        foreach ($due as $broadcast) {
            try {
                if ($broadcast->isRecurring()) {
                    if ($this->materializeOccurrence($broadcast) !== null) {
                        $materialized++;
                    }
                } else {
                    $this->lifecycle->start($broadcast);
                    $started++;
                }
            } catch (InvalidBroadcastTransition $e) {
                // Another scheduler tick or a concurrent admin click won the race.
                Log::info('broadcast.scheduler.race_lost', [
                    'broadcast_id' => $broadcast->id,
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        return ['started' => $started, 'materialized' => $materialized];
    }

    /**
     * Atomically clone one occurrence from a recurring template parent.
     * Returns null when a concurrent scheduler already handled it.
     */
    private function materializeOccurrence(Broadcast $parent): ?Broadcast
    {
        try {
            $child = DB::transaction(function () use ($parent): ?Broadcast {
                /** @var Broadcast|null $locked */
                $locked = Broadcast::query()->whereKey($parent->id)->lockForUpdate()->first();

                // Re-verify under the lock: a concurrent scheduler may have
                // advanced the parent between our read and this lock.
                if ($locked === null
                    || ! $locked->isRecurring()
                    || $locked->status !== BroadcastStatus::Scheduled
                    || $locked->scheduled_at === null
                    || $locked->scheduled_at->isFuture()) {
                    return null;
                }

                $occurrenceAt = $locked->scheduled_at;

                // Occurrence idempotency: skip if this occurrence already exists
                // (e.g. crash between child creation and parent advancement).
                $child = null;

                if (! Broadcast::query()
                    ->where('parent_broadcast_id', $locked->id)
                    ->where('occurrence_at', $occurrenceAt)
                    ->exists()) {
                    $child = $this->lifecycle->duplicate($locked);
                    $child->forceFill([
                        'parent_broadcast_id' => $locked->id,
                        'occurrence_at' => $occurrenceAt,
                    ])->save();
                }

                $locked->forceFill([
                    'scheduled_at' => $this->nextOccurrence->next($locked->recurrence, now()),
                ])->save();

                return $child;
            });
        } catch (QueryException $e) {
            // Unique (parent, occurrence_at) violation: the DB-level defense
            // fired — a concurrent process created this occurrence first.
            if ((string) $e->getCode() === '23505') {
                Log::info('broadcast.recurrence.occurrence_race_lost', ['parent_id' => $parent->id]);

                return null;
            }

            throw $e;
        }

        if ($child === null) {
            return null;
        }

        // Outside the transaction: starting is itself an atomic status claim.
        $this->lifecycle->start($child);

        Log::info('broadcast.recurrence.materialized', [
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'occurrence_at_utc' => $child->occurrence_at?->toIso8601String(),
            'next_occurrence_utc' => $parent->refresh()->scheduled_at?->toIso8601String(),
        ]);

        return $child;
    }
}
