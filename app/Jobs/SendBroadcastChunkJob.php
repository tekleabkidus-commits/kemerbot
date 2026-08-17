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
 * Self-chaining chunk worker (spec §7): claim a chunk via atomic LPOP, send
 * it, apply one counter UPDATE, dispatch the next link. Checks the
 * cancellation flag every chunk; a worker death loses at most one chunk of
 * counter updates (documented residual edge, spec §7) and the next dispatch
 * simply consumes what remains in Redis.
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

        $chunk = $snapshot->popChunk($broadcast->id, (int) config('telegram.broadcast_chunk_size'));

        if ($chunk === []) {
            $lifecycle->complete($broadcast);

            return;
        }

        $sender->sendChunk($broadcast, $chunk);

        self::dispatch($this->broadcastId)->onQueue(config('telegram.queues.broadcast'));
    }
}
