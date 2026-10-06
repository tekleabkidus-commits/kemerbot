<?php

declare(strict_types=1);

namespace App\Services\Broadcasts;

use App\Models\Broadcast;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Durable audience and outcome ledger. Redis outages cannot lose recipients. */
final class AudienceSnapshot
{
    public function __construct(private readonly AudienceQuery $audience) {}

    public function build(Broadcast $broadcast): int
    {
        if ($broadcast->snapshot_built_at) {
            return $this->rows($broadcast->id)->where('status', '!=', 'holdout')->count();
        }
        $hasAlternative = (bool) array_filter($broadcast->experiment['text_b'] ?? [], 'filled');
        $highWater = User::query()->max('id') ?? 0;
        $this->audience->build($broadcast->audience_filter)->where('id', '<=', $highWater)->select(['id', 'tg_chat_id'])->chunkById(500, function ($users) use ($broadcast, $hasAlternative) {
            $rows = [];
            foreach ($users as $user) {
                $bucket = hexdec(substr(hash('sha256', $broadcast->id.':'.$user->id), 0, 6)) % 100;
                $rows[] = ['broadcast_id' => $broadcast->id, 'user_id' => $user->id, 'chat_id' => $user->tg_chat_id,
                    'status' => $bucket < (int) ($broadcast->experiment['holdout_percent'] ?? 0) ? 'holdout' : 'ready',
                    'variant' => $hasAlternative && $bucket % 2 !== 0 ? 'b' : 'a', 'available_at' => now(), 'created_at' => now(), 'updated_at' => now()];
            }
            DB::table('broadcast_recipients')->insertOrIgnore($rows);
        });
        $count = $this->rows($broadcast->id)->where('status', '!=', 'holdout')->count();
        $broadcast->forceFill(['snapshot_built_at' => now()])->save();

        return $count;
    }

    public function claimBatch(int $id, string $consumer, int $size): array
    {
        return DB::transaction(function () use ($id, $size) {
            $rows = $this->rows($id)->where(function ($q) {
                $q->where(function ($q) {
                    $q->where('status', 'ready')->where('available_at', '<=', now());
                })
                    ->orWhere(function ($q) {
                        $q->where('status', 'processing')->where('claimed_at', '<=', now()->subSeconds(config('telegram.broadcast_claim_timeout_seconds')));
                    });
            })->orderBy('id')->limit(max(1, $size))->lock('for update skip locked')->get();
            $entries = [];
            foreach ($rows as $row) {
                $token = (string) Str::uuid();
                DB::table('broadcast_recipients')->where('id', $row->id)->update(['status' => 'processing', 'claim_token' => $token, 'claimed_at' => now(), 'updated_at' => now()]);
                $entries[] = ['id' => (string) $row->id, 'user_id' => $row->user_id, 'chat_id' => $row->chat_id, 'attempt' => $row->attempt, 'token' => $token, 'variant' => $row->variant];
            }

            return $entries;
        });
    }

    public function finish(int $id, array $entry, string $outcome): bool
    {
        return DB::transaction(function () use ($id, $entry, $outcome) {
            $changed = $this->rows($id)->where('id', $entry['id'])->where('status', 'processing')->where('claim_token', $entry['token'])
                ->update(['status' => $outcome, 'sent_at' => $outcome === 'sent' ? now() : null, 'claim_token' => null, 'updated_at' => now()]);
            if (! $changed) {
                return false;
            }
            $column = in_array($outcome, ['sent', 'blocked', 'skipped'], true) ? $outcome : 'failed';
            DB::table('broadcasts')->where('id', $id)->increment($column, 1, ['updated_at' => now()]);

            return true;
        });
    }

    public function defer(int $id, array $entry, int $seconds = 300, bool $attempt = false): void
    {
        $this->rows($id)->where('id', $entry['id'])->where('claim_token', $entry['token'])->update([
            'status' => 'ready', 'claim_token' => null, 'available_at' => now()->addSeconds($seconds), 'attempt' => $entry['attempt'] + ($attempt ? 1 : 0), 'updated_at' => now()]);
    }

    public function requeueForRetry(int $id, array $entry): void
    {
        $backoff = config('telegram.send_backoff_seconds');
        $this->defer($id, $entry, ($backoff[min($entry['attempt'] - 1, count($backoff) - 1)] ?? 120) + random_int(0, 3), true);
    }

    public function isDone(int $id, int $user): bool
    {
        return $this->rows($id)->where('user_id', $user)->whereIn('status', ['sent', 'blocked', 'failed', 'skipped', 'holdout'])->exists();
    }

    public function markDone(int $id, int $user): bool
    {
        return (bool) $this->rows($id)->where('user_id', $user)->whereIn('status', ['ready', 'processing'])->update(['status' => 'sent']);
    }

    public function ack(int $id, string $entry): void {}

    public function outstanding(int $id): int
    {
        return $this->rows($id)->whereIn('status', ['ready', 'processing'])->count();
    }

    public function remaining(int $id): int
    {
        return $this->outstanding($id);
    }

    public function cleanup(int $id): void
    {
        $this->rows($id)->whereIn('status', ['ready', 'processing'])->update(['status' => 'cancelled', 'claim_token' => null, 'updated_at' => now()]);
    }

    public function registeredSnapshotIds(): array
    {
        return DB::table('broadcast_recipients')->distinct()->orderBy('broadcast_id')->pluck('broadcast_id')->map(fn ($id) => (int) $id)->all();
    }

    private function rows(int $id)
    {
        return DB::table('broadcast_recipients')->where('broadcast_id', $id);
    }
}
