<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Models\Poll;
use App\Models\PollInstance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Poll update ingestion (spec §5.8, §14), correlated through poll_instances.
 *
 * Counting source: `poll` updates only. Telegram reports the authoritative
 * per-instance totals there for BOTH anonymous and non-anonymous polls, which
 * also handles vote retractions correctly. Per-instance latest counts live in
 * a Redis hash; the parent's answer_counts is their sum.
 *
 * `poll_answer` updates (non-anonymous only) identify the voter — used for
 * activity tracking (a vote is an inbound interaction), never for counting,
 * so the two update types can never double-count.
 */
final class PollService
{
    public function __construct(private readonly UserService $users) {}

    public function ingestPollUpdate(array $tgPoll): void
    {
        $tgPollId = $tgPoll['id'] ?? null;

        if ($tgPollId === null) {
            return;
        }

        $instance = PollInstance::query()->where('tg_poll_id', (string) $tgPollId)->first();

        if ($instance === null) {
            Log::info('telegram.poll.unknown_instance', ['tg_poll_id' => (string) $tgPollId]);

            return;
        }

        $counts = array_map(
            fn (array $option): int => (int) ($option['voter_count'] ?? 0),
            array_values($tgPoll['options'] ?? []),
        );

        $key = $this->countsKey($instance->poll_id);
        Redis::hset($key, (string) $tgPollId, json_encode($counts));

        $this->recomputeTotals($instance->poll_id);
    }

    public function ingestPollAnswer(array $pollAnswer): void
    {
        $tgPollId = $pollAnswer['poll_id'] ?? null;
        $from = $pollAnswer['user'] ?? null;

        if ($tgPollId === null || ! PollInstance::query()->where('tg_poll_id', (string) $tgPollId)->exists()) {
            return;
        }

        // A vote is an inbound interaction: activity + blocked recovery.
        if (is_array($from) && isset($from['id'])) {
            $user = User::query()->where('tg_chat_id', (int) $from['id'])->first();

            if ($user !== null) {
                $this->users->recordActivity($user);
            }
        }
    }

    private function recomputeTotals(int $pollId): void
    {
        $perInstance = Redis::hgetall($this->countsKey($pollId));
        $totals = [];

        foreach ($perInstance as $json) {
            foreach ((array) json_decode((string) $json, true) as $index => $count) {
                $totals[(string) $index] = ($totals[(string) $index] ?? 0) + (int) $count;
            }
        }

        DB::transaction(function () use ($pollId, $totals) {
            Poll::query()->whereKey($pollId)->lockForUpdate()->first()?->update([
                'answer_counts' => $totals,
            ]);
        });
    }

    private function countsKey(int $pollId): string
    {
        return "poll:{$pollId}:counts";
    }
}
