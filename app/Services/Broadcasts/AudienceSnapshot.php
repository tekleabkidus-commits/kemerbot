<?php

declare(strict_types=1);

namespace App\Services\Broadcasts;

use App\Models\Broadcast;
use Illuminate\Support\Facades\Redis;

/**
 * Redis audience snapshot (spec §7): logically frozen at start. A cursor query
 * pushes "userId:chatId" pairs into one list; chunk jobs LPOP atomically, so
 * consumption is idempotent and worker restarts simply leave the remainder
 * for the next worker. Memory: ~8–16 bytes/id ⇒ 100k ≈ 2–4 MB, 1M ≈ 20–40 MB
 * with overhead — acceptable. Keys die with the broadcast (terminal states)
 * plus a 48h orphan sweep (config telegram.snapshot_orphan_hours, Batch 4 job).
 */
final class AudienceSnapshot
{
    private const PUSH_BATCH = 500;

    public function __construct(private readonly AudienceQuery $audience) {}

    /** Build the snapshot; returns the frozen audience size. */
    public function build(Broadcast $broadcast): int
    {
        $key = $this->key($broadcast->id);

        // A retried prepare job must not double-fill the list.
        Redis::del($key);

        $count = 0;
        $buffer = [];

        $query = $this->audience->build($broadcast->audience_filter)
            ->select(['id', 'tg_chat_id'])
            ->orderBy('id');

        foreach ($query->cursor() as $user) {
            $buffer[] = $user->id.':'.$user->tg_chat_id;
            $count++;

            if (count($buffer) >= self::PUSH_BATCH) {
                Redis::rpush($key, ...$buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            Redis::rpush($key, ...$buffer);
        }

        // TTL backstop: no legitimate broadcast runs this long (100k @ 25/s
        // ≈ 67 min), so even a crashed run can't leak the key forever.
        if ($count > 0) {
            $ttl = (int) config('telegram.snapshot_orphan_hours') * 3600;
            Redis::expire($key, $ttl);
            Redis::expire($this->attemptsKey($broadcast->id), $ttl);
        }

        return $count;
    }

    /**
     * Atomically claim the next chunk. Each entry: [userId, tgChatId].
     *
     * @return list<array{0: int, 1: int}>
     */
    public function popChunk(int $broadcastId, int $size): array
    {
        $chunk = [];

        for ($i = 0; $i < $size; $i++) {
            $entry = Redis::lpop($this->key($broadcastId));

            if ($entry === null || $entry === false) {
                break;
            }

            [$userId, $chatId] = explode(':', (string) $entry, 2);
            $chunk[] = [(int) $userId, (int) $chatId];
        }

        return $chunk;
    }

    /** Re-queue a retryable user at the tail and bump their attempt count. */
    public function requeueForRetry(int $broadcastId, int $userId, int $chatId): int
    {
        Redis::rpush($this->key($broadcastId), $userId.':'.$chatId);

        return (int) Redis::hincrby($this->attemptsKey($broadcastId), (string) $userId, 1);
    }

    public function attemptsFor(int $broadcastId, int $userId): int
    {
        return (int) (Redis::hget($this->attemptsKey($broadcastId), (string) $userId) ?: 0);
    }

    public function remaining(int $broadcastId): int
    {
        return (int) Redis::llen($this->key($broadcastId));
    }

    public function cleanup(int $broadcastId): void
    {
        Redis::del($this->key($broadcastId), $this->attemptsKey($broadcastId));
    }

    private function key(int $broadcastId): string
    {
        return "broadcast:{$broadcastId}:audience";
    }

    private function attemptsKey(int $broadcastId): string
    {
        return "broadcast:{$broadcastId}:attempts";
    }
}
