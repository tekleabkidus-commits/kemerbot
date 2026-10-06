<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Models\PollInstance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/** Durable previous counts and totals commit together under a row lock. */
final class PollService
{
    public function __construct(private readonly UserService $users) {}

    public function ingestPollUpdate(array $tgPoll, ?int $updateId = null): void
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

        $newCounts = array_map(
            fn (array $option): int => (int) ($option['voter_count'] ?? 0),
            array_values($tgPoll['options'] ?? []),
        );

        DB::transaction(function () use ($instance, $tgPollId, $newCounts, $updateId) {
            $instance = PollInstance::query()->lockForUpdate()->findOrFail($instance->id);
            if ($updateId !== null && $instance->last_update_id !== null && $updateId <= $instance->last_update_id) {
                return;
            }
            $previous = $instance->previous_counts;
            if ($previous === null) {
                $legacy = Redis::get($this->prevKey((string) $tgPollId));
                $previous = $legacy ? json_decode($legacy, true) : [];
            }

            $deltas = [];

            foreach ($newCounts as $index => $count) {
                $delta = $count - (int) ($previous[$index] ?? 0);

                if ($delta !== 0) {
                    $deltas[$index] = $delta;
                }
            }

            if ($deltas === []) {
                $instance->forceFill(['previous_counts' => $newCounts, 'last_update_id' => $updateId ?? $instance->last_update_id])->save();

                return;
            }

            $this->applyDeltas($instance->poll_id, $deltas);
            $instance->forceFill(['previous_counts' => $newCounts, 'last_update_id' => $updateId ?? $instance->last_update_id])->save();
        });
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

    /** Seed the per-instance previous counts (state migration / tests). */
    public function seedPreviousCounts(string $tgPollId, array $counts): void
    {
        PollInstance::query()->where('tg_poll_id', $tgPollId)->update(['previous_counts' => json_encode(array_values($counts))]);
        Redis::set(
            $this->prevKey($tgPollId),
            json_encode(array_values(array_map('intval', $counts))),
            'EX',
            $this->prevTtlSeconds(),
        );
    }

    /**
     * One row-atomic UPDATE applying all option deltas. answer_counts is a
     * jsonb ARRAY (index = option position); the whole array is rebuilt from
     * the row's current value in a single statement, so concurrent updates
     * serialize on the row lock and can never corrupt totals. O(options).
     *
     * @param  array<int, int>  $deltas  option index => signed delta
     */
    private function applyDeltas(int $pollId, array $deltas): void
    {
        $values = [];
        $pairBindings = [];

        foreach ($deltas as $index => $delta) {
            $values[] = '(?::int, ?::int)';
            array_push($pairBindings, $index, $delta);
        }

        // Placeholder order follows the SQL text: series upper bound first,
        // then the delta pairs, then the row id.
        $bindings = [max(array_keys($deltas)), ...$pairBindings, $pollId];

        DB::update(
            'UPDATE polls SET answer_counts = (
                SELECT COALESCE(
                    jsonb_agg(COALESCE((polls.answer_counts ->> idx.n)::int, 0) + COALESCE(d.delta, 0) ORDER BY idx.n),
                    \'[]\'::jsonb
                )
                FROM generate_series(
                    0,
                    GREATEST(COALESCE(jsonb_array_length(COALESCE(polls.answer_counts, \'[]\'::jsonb)), 0) - 1, ?)
                ) AS idx(n)
                LEFT JOIN (VALUES '.implode(', ', $values).') AS d(i, delta) ON d.i = idx.n
            ), updated_at = NOW() WHERE id = ?',
            $bindings,
        );
    }

    private function prevKey(string $tgPollId): string
    {
        return 'poll:prev:'.$tgPollId;
    }

    private function prevTtlSeconds(): int
    {
        return max(1, (int) config('telegram.poll_prev_ttl_days')) * 86400;
    }
}
