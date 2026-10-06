<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Services\Broadcasts\AudienceSnapshot;
use App\Services\Broadcasts\BroadcastChunkSender;
use App\Services\Broadcasts\BroadcastLifecycle;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/** Sends leased durable recipients; recovery resumes interrupted work. */
class SendBroadcastChunkJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

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

        if (! $broadcast->snapshot_built_at) {
            $lifecycle->fail($broadcast, 'Audience ledger is missing. Review the campaign before creating a new send.');

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
            } elseif (config('queue.default') !== 'sync') {
                $next = DB::table('broadcast_recipients')->where('broadcast_id', $broadcast->id)->where('status', 'ready')->min('available_at');
                if ($next) {
                    self::dispatch($this->broadcastId)->onQueue(config('telegram.queues.broadcast'))->delay(Carbon::parse($next)->max(now()->addSeconds(5)));
                }
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
