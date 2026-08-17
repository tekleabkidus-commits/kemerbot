<?php

declare(strict_types=1);

namespace App\Services\Broadcasts;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use Illuminate\Support\Facades\Log;

/**
 * Runs every minute from the Laravel scheduler (spec §7): promotes due
 * one-off scheduled broadcasts, and for recurring definitions materializes a
 * one-off child occurrence and advances the parent to its next slot
 * (spec §4.3 — recurring campaigns never touch the automation engine).
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
                    $this->materializeOccurrence($broadcast);
                    $materialized++;
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
     * The recurring row acts as a template: each due tick clones a one-off
     * child (stats live on the child) and re-arms the parent for the next
     * occurrence in Addis time.
     */
    private function materializeOccurrence(Broadcast $parent): void
    {
        $child = $this->lifecycle->duplicate($parent);

        $next = $this->nextOccurrence->next($parent->recurrence, now());
        $parent->forceFill(['scheduled_at' => $next])->save();

        $this->lifecycle->start($child);

        Log::info('broadcast.recurrence.materialized', [
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'next_occurrence_utc' => $next->toIso8601String(),
        ]);
    }
}
