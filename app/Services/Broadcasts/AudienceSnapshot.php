<?php

declare(strict_types=1);

namespace App\Services\Broadcasts;

use App\Models\Broadcast;
use Illuminate\Support\Facades\Redis;

/**
 * Redis STREAM audience snapshot (spec §7, redesigned per HARDENING §3).
 *
 * The audience is frozen into `broadcast:{id}:stream` and consumed through a
 * consumer group with durable claim → process → acknowledge semantics:
 *
 *   XADD (freeze) → XREADGROUP (claim) → send → XACK + XDEL (finish)
 *
 * A worker that dies after claiming leaves its entries in the group's pending
 * list; the next worker reclaims them via XAUTOCLAIM once they have been idle
 * longer than the configurable claim timeout. Recipients are therefore never
 * lost to a crash — at-least-once processing.
 *
 * Idempotent bookkeeping: `broadcast:{id}:done` (SET of user ids) records
 * each finished recipient. A reclaimed entry whose user is already in the
 * done set is acknowledged without re-sending or re-counting, so the
 * duplicate window narrows to a crash between Telegram accepting a message
 * and the SADD — the unavoidable ambiguity documented in spec §7.
 *
 * Retryable failures are re-queued as fresh entries at the stream tail with
 * an incremented attempt field (positional backoff, no sleeping workers).
 *
 * Keys carry a TTL backstop and are tracked in the `broadcast:snapshots`
 * registry SET so the sweeper never needs Redis KEYS (HARDENING §24).
 */
final class AudienceSnapshot
{
    private const PUSH_BATCH = 500;

    private const GROUP = 'senders';

    private const REGISTRY_KEY = 'broadcast:snapshots';

    public function __construct(private readonly AudienceQuery $audience) {}

    /** Freeze the audience; returns its size. Retry-safe: resets its keys. */
    public function build(Broadcast $broadcast): int
    {
        $stream = $this->streamKey($broadcast->id);
        $done = $this->doneKey($broadcast->id);

        Redis::del($stream, $done);
        Redis::xgroup('CREATE', $stream, self::GROUP, '0', true);

        $count = 0;
        $buffer = [];

        $query = $this->audience->build($broadcast->audience_filter)
            ->select(['id', 'tg_chat_id'])
            ->orderBy('id');

        foreach ($query->cursor() as $user) {
            $buffer[] = ['u' => (string) $user->id, 'c' => (string) $user->tg_chat_id, 'a' => '1'];
            $count++;

            if (count($buffer) >= self::PUSH_BATCH) {
                $this->pushBatch($stream, $buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $this->pushBatch($stream, $buffer);
        }

        $ttl = (int) config('telegram.snapshot_orphan_hours') * 3600;
        Redis::expire($stream, $ttl);
        Redis::sadd(self::REGISTRY_KEY, (string) $broadcast->id);

        return $count;
    }

    /**
     * Claim the next batch: first reclaim entries abandoned by dead workers
     * (idle > claim timeout), then read fresh ones.
     *
     * @return list<array{id: string, user_id: int, chat_id: int, attempt: int}>
     */
    public function claimBatch(int $broadcastId, string $consumer, int $size): array
    {
        $stream = $this->streamKey($broadcastId);
        $minIdleMs = (int) config('telegram.broadcast_claim_timeout_seconds') * 1000;

        // Missing stream/group (TTL-expired key, legacy pre-stream broadcast):
        // recreate an empty group so the chain terminates cleanly instead of
        // crash-looping on NOGROUP; outstanding() will read 0 → complete.
        try {
            $reclaimed = Redis::xautoclaim($stream, self::GROUP, $consumer, $minIdleMs, '0-0', $size);
        } catch (\Throwable $e) {
            if (! str_contains($e->getMessage(), 'NOGROUP')) {
                throw $e;
            }

            Redis::xgroup('CREATE', $stream, self::GROUP, '0', true);
            $reclaimed = Redis::xautoclaim($stream, self::GROUP, $consumer, $minIdleMs, '0-0', $size);
        }
        $entries = $this->normalize(is_array($reclaimed) ? ($reclaimed[1] ?? []) : []);

        if (count($entries) >= $size) {
            return $entries;
        }

        $fresh = Redis::xreadgroup(self::GROUP, $consumer, [$stream => '>'], $size - count($entries));

        if (is_array($fresh)) {
            foreach ($fresh as $streamEntries) {
                $entries = [...$entries, ...$this->normalize($streamEntries)];
            }
        }

        return $entries;
    }

    /** Acknowledge and remove a finished entry. */
    public function ack(int $broadcastId, string $entryId): void
    {
        $stream = $this->streamKey($broadcastId);
        Redis::xack($stream, self::GROUP, [$entryId]);
        Redis::xdel($stream, [$entryId]);
    }

    /** Re-queue a retryable recipient at the tail with attempt+1. */
    public function requeueForRetry(int $broadcastId, array $entry): void
    {
        $this->ack($broadcastId, $entry['id']);

        Redis::xadd($this->streamKey($broadcastId), '*', [
            'u' => (string) $entry['user_id'],
            'c' => (string) $entry['chat_id'],
            'a' => (string) ($entry['attempt'] + 1),
        ]);
    }

    /**
     * Idempotent bookkeeping: returns true only the FIRST time a recipient is
     * finished — a reclaimed duplicate must neither re-send nor re-count.
     */
    public function markDone(int $broadcastId, int $userId): bool
    {
        $done = $this->doneKey($broadcastId);
        $newlyDone = (int) Redis::sadd($done, (string) $userId) === 1;

        Redis::expire($done, (int) config('telegram.snapshot_orphan_hours') * 3600);

        return $newlyDone;
    }

    public function isDone(int $broadcastId, int $userId): bool
    {
        return (bool) Redis::sismember($this->doneKey($broadcastId), (string) $userId);
    }

    /**
     * Entries not yet finished (unread + claimed-but-unacked). Entries are
     * XDELed on ack, so stream length is exactly the outstanding count.
     */
    public function outstanding(int $broadcastId): int
    {
        return (int) Redis::xlen($this->streamKey($broadcastId));
    }

    /** Back-compat alias used by ops/tests. */
    public function remaining(int $broadcastId): int
    {
        return $this->outstanding($broadcastId);
    }

    public function cleanup(int $broadcastId): void
    {
        Redis::del($this->streamKey($broadcastId), $this->doneKey($broadcastId));
        Redis::srem(self::REGISTRY_KEY, (string) $broadcastId);
    }

    /** @return list<int> broadcast ids with live snapshot keys (no KEYS scan) */
    public function registeredSnapshotIds(): array
    {
        return array_map('intval', Redis::smembers(self::REGISTRY_KEY) ?: []);
    }

    private function pushBatch(string $stream, array $buffer): void
    {
        Redis::pipeline(function ($pipe) use ($stream, $buffer): void {
            foreach ($buffer as $fields) {
                $pipe->xadd($stream, '*', $fields);
            }
        });
    }

    /** @return list<array{id: string, user_id: int, chat_id: int, attempt: int}> */
    private function normalize(array $rawEntries): array
    {
        $entries = [];

        foreach ($rawEntries as $id => $fields) {
            if (! is_array($fields) || ! isset($fields['u'], $fields['c'])) {
                continue;
            }

            $entries[] = [
                'id' => (string) $id,
                'user_id' => (int) $fields['u'],
                'chat_id' => (int) $fields['c'],
                'attempt' => max(1, (int) ($fields['a'] ?? 1)),
            ];
        }

        return $entries;
    }

    private function streamKey(int $broadcastId): string
    {
        return "broadcast:{$broadcastId}:stream";
    }

    private function doneKey(int $broadcastId): string
    {
        return "broadcast:{$broadcastId}:done";
    }
}
