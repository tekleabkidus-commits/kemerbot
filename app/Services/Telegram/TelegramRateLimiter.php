<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

/**
 * Global Redis token bucket for ALL Telegram sends (spec §10): N workers still
 * collectively respect the cap. The env value is the hard ceiling; the DB
 * setting `telegram.send_rate` may lower it at runtime. Honors 429 retry_after
 * by pausing the whole bucket — the only place send-throttling sleeps live.
 */
final class TelegramRateLimiter
{
    private const WINDOW_KEY_PREFIX = 'telegram:rl:';

    private const PAUSE_KEY = 'telegram:rl:paused_until';

    public function __construct(private readonly SettingsService $settings) {}

    public function rate(): int
    {
        $ceiling = max(1, (int) config('telegram.send_rate'));
        $configured = (int) ($this->settings->get('telegram.send_rate') ?? $ceiling);

        return max(1, min($ceiling, $configured));
    }

    /** Non-blocking: consume one send slot in the current one-second window. */
    public function tryAcquire(): bool
    {
        if ($this->isPaused()) {
            return false;
        }

        $key = self::WINDOW_KEY_PREFIX.now()->getTimestamp();
        $count = (int) Redis::incr($key);

        if ($count === 1) {
            Redis::expire($key, 3);
        }

        return $count <= $this->rate();
    }

    /** Blocking: wait for a slot; throws if none frees up within the deadline. */
    public function acquire(int $maxWaitSeconds = 60): void
    {
        $deadline = microtime(true) + $maxWaitSeconds;

        while (! $this->tryAcquire()) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException("Telegram rate limiter: no slot within {$maxWaitSeconds}s");
            }

            usleep(50_000);
        }
    }

    /** Pause the global bucket, e.g. for a Telegram 429 retry_after. */
    public function pause(int $seconds): void
    {
        $until = now()->getTimestamp() + max(1, $seconds);
        $current = (int) (Redis::get(self::PAUSE_KEY) ?? 0);

        if ($until > $current) {
            Redis::setex(self::PAUSE_KEY, max(1, $seconds) + 1, (string) $until);
        }
    }

    public function isPaused(): bool
    {
        $until = Redis::get(self::PAUSE_KEY);

        return $until !== null && (int) $until > now()->getTimestamp();
    }
}
