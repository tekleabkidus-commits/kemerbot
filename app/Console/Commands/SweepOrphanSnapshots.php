<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Services\Broadcasts\AudienceSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Active cleanup for snapshot keys whose broadcast is gone or terminal
 * (spec §7) — e.g. after a crash mid-prepare. The 48h TTL set at build time
 * is the passive backstop; this reclaims memory promptly.
 */
class SweepOrphanSnapshots extends Command
{
    protected $signature = 'broadcasts:sweep-orphans';

    protected $description = 'Delete Redis audience snapshots for missing or terminal broadcasts';

    public function handle(AudienceSnapshot $snapshot): int
    {
        $swept = 0;

        foreach (Redis::keys('broadcast:*:audience') as $key) {
            // keys() returns prefixed names; the id sits between the colons.
            if (preg_match('/broadcast:(\d+):audience$/', $key, $m) !== 1) {
                continue;
            }

            $broadcastId = (int) $m[1];
            $broadcast = Broadcast::query()->find($broadcastId);

            $active = $broadcast !== null && in_array($broadcast->status, [
                BroadcastStatus::Preparing,
                BroadcastStatus::Sending,
                BroadcastStatus::Paused,
            ], true);

            if (! $active) {
                $snapshot->cleanup($broadcastId);
                $swept++;
            }
        }

        if ($swept > 0) {
            Log::info('broadcast.snapshots_swept', ['count' => $swept]);
        }

        $this->info("Swept {$swept} orphaned snapshot(s).");

        return self::SUCCESS;
    }
}
