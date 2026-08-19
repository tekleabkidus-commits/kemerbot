<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;

/**
 * Diagnostic no-op: proves a worker is consuming a given queue lane by
 * stamping a short-lived Redis key (kemerbot:diagnose reads it back).
 */
class WorkerPingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly string $lane) {}

    public function handle(): void
    {
        Redis::setex('kemerbot:diag:ping:'.$this->lane, 300, now()->toIso8601String());
    }
}
