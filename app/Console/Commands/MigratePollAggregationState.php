<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Poll;
use App\Models\PollInstance;
use App\Services\Bot\PollService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

/**
 * One-off deploy step for HARDENING §5: converts the legacy per-poll counts
 * hash (poll:{poll_id}:counts, field per tg_poll_id) into the new
 * per-instance previous-counts keys. Without this, the first post-deploy
 * poll update per instance would compute its delta against zero and
 * double-add historical votes to the DB totals. Idempotent: the legacy hash
 * is deleted after conversion, so a re-run finds nothing to do.
 */
class MigratePollAggregationState extends Command
{
    protected $signature = 'polls:migrate-aggregation-state';

    protected $description = 'Convert legacy poll count hashes to durable per-instance previous counts';

    public function handle(PollService $polls): int
    {
        $migrated = 0;

        foreach (Poll::query()->pluck('id') as $pollId) {
            $legacyKey = "poll:{$pollId}:counts";
            $perInstance = Redis::hgetall($legacyKey);

            if ($perInstance === [] || $perInstance === false) {
                continue;
            }

            foreach ($perInstance as $tgPollId => $json) {
                $counts = (array) json_decode((string) $json, true);
                $polls->seedPreviousCounts((string) $tgPollId, $counts);
                $migrated++;
            }

            Redis::del($legacyKey);
        }

        PollInstance::query()->whereNull('previous_counts')->orderBy('id')->chunkById(500, function ($instances) use ($polls, &$migrated) {
            foreach ($instances as $instance) {
                $json = Redis::get('poll:prev:'.$instance->tg_poll_id);
                if (is_string($json)) {
                    $polls->seedPreviousCounts($instance->tg_poll_id, (array) json_decode($json, true));
                    $migrated++;
                }
            }
        });
        $this->info("Seeded previous counts for {$migrated} poll instance(s).");

        return self::SUCCESS;
    }
}
