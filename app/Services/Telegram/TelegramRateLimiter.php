<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Services\SettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

final class TelegramRateLimiter
{
    private const ACQUIRE = <<<'LUA'
 local t=tonumber(ARGV[1])
 local pause=tonumber(redis.call('GET',KEYS[1]) or '0')
 local next=tonumber(redis.call('GET',KEYS[2]) or '0')
 local chat=tonumber(redis.call('GET',KEYS[3]) or '0')
 if pause>t or next>t or chat>t then return 0 end
 redis.call('PSETEX',KEYS[2],300000,t+tonumber(ARGV[2]))
 if ARGV[3]=='1' then redis.call('PSETEX',KEYS[3],300000,t+1000) end
 return 1
 LUA;

    private float $clockStart;

    public function __construct(private readonly SettingsService $settings)
    {
        $this->clockStart = microtime(true);
    }

    private function clock(): int
    {
        return now()->getTimestampMs() + (Carbon::hasTestNow() ? (int) ((microtime(true) - $this->clockStart) * 1000) : 0);
    }

    public function rate(): int
    {
        $ceiling = max(1, (int) config('telegram.send_rate'));

        return max(1, min($ceiling, (int) $this->settings->get('telegram.send_rate', $ceiling)));
    }

    public function tryAcquire(?int $chatId = null): bool
    {
        return (bool) Redis::eval(self::ACQUIRE, 3, 'telegram:rl:paused_until', 'telegram:rl:next', 'telegram:rl:chat:'.($chatId ?? 'none'), (string) $this->clock(), (string) (1000 / $this->rate()), $chatId === null ? '0' : '1');
    }

    public function acquire(int $maxWaitSeconds = 60, ?int $chatId = null): void
    {
        $deadline = microtime(true) + $maxWaitSeconds;
        while (! $this->tryAcquire($chatId)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Telegram send capacity unavailable');
            }usleep(50000);
        }
    }

    public function pause(int $seconds): void
    {
        Redis::eval("local v=tonumber(redis.call('GET',KEYS[1]) or '0'); if tonumber(ARGV[1])>v then redis.call('PSETEX',KEYS[1],ARGV[2],ARGV[1]) end; return 1", 1, 'telegram:rl:paused_until', (string) ($this->clock() + max(1, $seconds) * 1000), (string) ((max(1, $seconds) + 1) * 1000));
    }

    public function isPaused(): bool
    {
        return (int) (Redis::get('telegram:rl:paused_until') ?? 0) > $this->clock();
    }
}
