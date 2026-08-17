<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Broadcast;
use App\Services\Broadcasts\BroadcastLifecycle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Freezes the audience snapshot and flips the broadcast into `sending`
 * (spec §7). Retry-safe: AudienceSnapshot::build() resets its own key.
 */
class PrepareBroadcastJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly int $broadcastId) {}

    public function handle(BroadcastLifecycle $lifecycle): void
    {
        $broadcast = Broadcast::query()->find($this->broadcastId);

        if ($broadcast !== null) {
            $lifecycle->prepare($broadcast);
        }
    }

    public function failed(): void
    {
        $broadcast = Broadcast::query()->find($this->broadcastId);

        if ($broadcast !== null) {
            app(BroadcastLifecycle::class)->fail($broadcast, 'prepare job exhausted retries');
        }
    }
}
