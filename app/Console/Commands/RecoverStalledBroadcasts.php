<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcastChunkJob;
use App\Models\Broadcast;
use App\Services\Broadcasts\AudienceSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * HARDENING §3 recovery job: a `sending` broadcast whose counter heartbeat
 * (updated_at) has gone quiet still has work in its stream — its chunk chain
 * died (worker crash, exhausted job retries). Re-dispatching the chain is
 * always safe: batch claims are atomic and finished recipients are skipped
 * via the done set. Also completes broadcasts whose stream drained but whose
 * completing worker died before the status flip.
 */
class RecoverStalledBroadcasts extends Command
{
    protected $signature = 'broadcasts:recover-stalled';

    protected $description = 'Re-dispatch chunk chains for sending broadcasts with no recent progress';

    public function handle(AudienceSnapshot $snapshot): int
    {
        $threshold = now()->subSeconds((int) config('telegram.broadcast_stall_seconds'));
        $recovered = 0;

        $stalled = Broadcast::query()
            ->where('status', BroadcastStatus::Sending)
            ->where('updated_at', '<', $threshold)
            ->get();

        foreach ($stalled as $broadcast) {
            // Touch the heartbeat so the next tick doesn't double-dispatch
            // before this dispatch had a chance to run.
            $broadcast->forceFill(['updated_at' => now()])->save();

            SendBroadcastChunkJob::dispatch($broadcast->id)
                ->onQueue(config('telegram.queues.broadcast'));

            $recovered++;

            Log::warning('broadcast.stall_recovered', [
                'broadcast_id' => $broadcast->id,
                'outstanding' => $snapshot->outstanding($broadcast->id),
            ]);
        }

        $this->info("Re-dispatched {$recovered} stalled broadcast chain(s).");

        return self::SUCCESS;
    }
}
