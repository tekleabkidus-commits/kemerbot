<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Services\Broadcasts\AudienceSnapshot;
use App\Services\Broadcasts\BroadcastChunkSender;
use App\Services\Broadcasts\BroadcastLifecycle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Self-chaining batch worker (spec §7, HARDENING §3). Claims a batch from the
 * audience stream's consumer group — reclaiming entries abandoned by dead
 * workers first — sends it, applies one counter UPDATE, and dispatches the
 * next link. Entries are acknowledged per recipient AFTER their outcome is
 * recorded, so a crash mid-batch leaves the unfinished entries in the pending
 * list to be reclaimed: recipients are never lost. The broadcast completes
 * only when the stream holds no unread and no pending entries.
 */
class SendBroadcastChunkJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public readonly int $broadcastId) {}

    public function handle(
        AudienceSnapshot $snapshot,
        BroadcastChunkSender $sender,
        BroadcastLifecycle $lifecycle,
    ): void {
        $broadcast = Broadcast::query()->find($this->broadcastId);

        if ($broadcast === null) {
            return;
        }

        // Cancellation / pause check per batch (spec §7).
        if ($broadcast->status !== BroadcastStatus::Sending) {
            if ($broadcast->status === BroadcastStatus::Cancelled) {
                $snapshot->cleanup($broadcast->id);
            }

            return;
        }

        $entries = $snapshot->claimBatch(
            $broadcast->id,
            $this->consumerName(),
            (int) config('telegram.broadcast_chunk_size'),
        );

        if ($entries === []) {
            // Complete ONLY when nothing is outstanding (unread + pending).
            // Entries claimed by another live worker keep the stream non-empty,
            // so a crashed worker can never make the campaign look finished.
            if ($snapshot->outstanding($broadcast->id) === 0) {
                $lifecycle->complete($broadcast);
            }

            // Outstanding entries belong to another consumer or are inside the
            // claim-timeout window; broadcasts:recover-stalled re-dispatches
            // the chain if that consumer never finishes.
            return;
        }

        $sender->sendChunk($broadcast, $entries);

        self::dispatch($this->broadcastId)->onQueue(config('telegram.queues.broadcast'));
    }

    private function consumerName(): string
    {
        return gethostname().':'.getmypid();
    }
}
