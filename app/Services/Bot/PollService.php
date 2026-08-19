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
 * HARDENING §5 — O(options) per update, never O(recipients):
 * each Telegram poll instance keeps only ITS OWN previous counts in one
 * TTL'd Redis key (`poll:prev:{tg_poll_id}`). An incoming `poll` update
 * atomically swaps prev→new (Lua GET+SET, so concurrent updates for the same
 * instance serialize and each sees a consistent predecessor), the per-option
 * deltas are applied to polls.answer_counts in a single row-atomic jsonb
 * UPDATE. Retractions produce negative deltas; a duplicate update produces
 * all-zero deltas and is a no-op. Nothing ever scans other instances.
 *
 * `poll_answer` updates (non-anonymous only) identify the voter — used for
 * activity tracking, never for counting, so the two update types can never
 * double-count.
 */
final class PollService
{
    /** Atomic prev-swap: returns the previous counts json ('' when absent). */
    private const SWAP_LUA = <<<'LUA'
        local old = redis.call('GET', KEYS[1])
        redis.call('SET', KEYS[1], ARGV[1], 'EX', ARGV[2])
        return old or ''
    LUA;

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

        $newCounts = array_map(
            fn (array $option): int => (int) ($option['voter_count'] ?? 0),
            array_values($tgPoll['options'] ?? []),
        );

        $previous = $this->swapPreviousCounts((string) $tgPollId, $newCounts);

        $deltas = [];

        foreach ($newCounts as $index => $count) {
            $delta = $count - (int) ($previous[$index] ?? 0);

            if ($delta !== 0) {
                $deltas[$index] = $delta;
            }
        }

        if ($deltas === []) {
            return; // Duplicate delivery of the same counts — idempotent no-op.
        }

        $this->applyDeltas($instance->poll_id, $deltas);
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
        Redis::set(
            $this->prevKey($tgPollId),
            json_encode(array_values(array_map('intval', $counts))),
            'EX',
            $this->prevTtlSeconds(),
        );
    }

    /** @return array<int, int> previous counts for this ONE instance */
    private function swapPreviousCounts(string $tgPollId, array $newCounts): array
    {
        $old = Redis::eval(
            self::SWAP_LUA,
            1,
            $this->prevKey($tgPollId),
            json_encode($newCounts),
            (string) $this->prevTtlSeconds(),
        );

        if (! is_string($old) || $old === '') {
            return [];
        }

        return array_map('intval', (array) json_decode($old, true));
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
