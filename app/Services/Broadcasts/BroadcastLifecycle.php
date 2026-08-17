<?php

declare(strict_types=1);

namespace App\Services\Broadcasts;

use App\Enums\BroadcastStatus;
use App\Jobs\PrepareBroadcastJob;
use App\Jobs\SendBroadcastChunkJob;
use App\Models\Broadcast;
use App\Services\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * THE single owner of broadcast status transitions (spec §7). No controller,
 * resource, or job writes `status` directly. Invalid transitions throw.
 *
 * draft → scheduled → preparing → sending → completed
 * exceptions: paused | cancelled | failed
 */
final class BroadcastLifecycle
{
    private const TRANSITIONS = [
        'draft' => ['scheduled', 'preparing', 'cancelled'],
        'scheduled' => ['preparing', 'cancelled', 'draft'],
        'preparing' => ['sending', 'cancelled', 'failed'],
        'sending' => ['completed', 'paused', 'cancelled', 'failed'],
        'paused' => ['sending', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
        'failed' => [],
    ];

    public function __construct(
        private readonly AudienceSnapshot $snapshot,
        private readonly AuditLogger $audit,
    ) {}

    // ---- admin-facing actions ----------------------------------------------

    public function schedule(Broadcast $broadcast, CarbonInterface $at, ?array $recurrence = null): void
    {
        if ($at->isPast()) {
            throw new InvalidBroadcastTransition('Cannot schedule a broadcast in the past');
        }

        $this->transition($broadcast, BroadcastStatus::Scheduled, [
            'scheduled_at' => $at,
            'recurrence' => $recurrence,
        ]);

        $this->audit->log('broadcast.scheduled', $broadcast, [
            'scheduled_at' => $at->toIso8601String(),
            'recurring' => $recurrence !== null,
        ]);
    }

    public function unschedule(Broadcast $broadcast): void
    {
        $this->transition($broadcast, BroadcastStatus::Draft, ['scheduled_at' => null]);
        $this->audit->log('broadcast.unscheduled', $broadcast);
    }

    /**
     * Kick off sending. The atomic status claim IS the double-send guard
     * (spec §7): a second Send click or a concurrent scheduler tick finds
     * zero rows to claim and throws.
     */
    public function start(Broadcast $broadcast): void
    {
        $claimed = Broadcast::query()
            ->whereKey($broadcast->id)
            ->whereIn('status', [BroadcastStatus::Draft->value, BroadcastStatus::Scheduled->value])
            ->update(['status' => BroadcastStatus::Preparing->value, 'started_at' => now()]);

        if ($claimed === 0) {
            throw InvalidBroadcastTransition::alreadyStarted($broadcast->id);
        }

        $broadcast->refresh();
        $this->audit->log('broadcast.started', $broadcast);

        PrepareBroadcastJob::dispatch($broadcast->id)
            ->onQueue(config('telegram.queues.broadcast'));
    }

    public function cancel(Broadcast $broadcast): void
    {
        $this->transition($broadcast, BroadcastStatus::Cancelled, ['cancelled_at' => now()]);
        $this->snapshot->cleanup($broadcast->id);

        $this->audit->log('broadcast.cancelled', $broadcast, [
            'sent_before_cancellation' => $broadcast->sent,
        ]);
    }

    public function pause(Broadcast $broadcast): void
    {
        $this->transition($broadcast, BroadcastStatus::Paused);
        $this->audit->log('broadcast.paused', $broadcast);
    }

    public function resume(Broadcast $broadcast): void
    {
        $this->transition($broadcast, BroadcastStatus::Sending);
        $this->audit->log('broadcast.resumed', $broadcast);

        SendBroadcastChunkJob::dispatch($broadcast->id)
            ->onQueue(config('telegram.queues.broadcast'));
    }

    /** Duplicate & resend from history (spec §5.3): deep copy as a fresh draft. */
    public function duplicate(Broadcast $broadcast): Broadcast
    {
        $copy = DB::transaction(function () use ($broadcast) {
            $copy = $broadcast->replicate([
                'status', 'audience_snapshot_count', 'scheduled_at', 'recurrence',
                'queued', 'sent', 'blocked', 'failed',
                'started_at', 'finished_at', 'cancelled_at',
            ]);
            $copy->status = BroadcastStatus::Draft;
            $copy->created_by = auth()->id() ?? $broadcast->created_by;
            $copy->save();

            foreach ($broadcast->translations as $translation) {
                $copy->translations()->create($translation->only(['lang', 'text', 'media_file_id']));
            }

            foreach ($broadcast->buttons as $button) {
                $newButton = $copy->buttons()->create($button->only(['row', 'position', 'kind', 'url']));

                foreach ($button->translations as $label) {
                    $newButton->translations()->create($label->only(['lang', 'label']));
                }
            }

            if ($broadcast->poll !== null) {
                $copy->poll()->create($broadcast->poll->only(['question', 'options', 'is_anonymous']));
            }

            return $copy;
        });

        $this->audit->log('broadcast.duplicated', $copy, ['from' => $broadcast->id]);

        return $copy;
    }

    // ---- engine-facing transitions -----------------------------------------

    /** Runs inside PrepareBroadcastJob: freeze the audience, start sending. */
    public function prepare(Broadcast $broadcast): void
    {
        if ($broadcast->status !== BroadcastStatus::Preparing) {
            Log::info('broadcast.prepare.skipped', ['broadcast_id' => $broadcast->id, 'status' => $broadcast->status->value]);

            return;
        }

        $count = $this->snapshot->build($broadcast);

        $this->transition($broadcast, BroadcastStatus::Sending, [
            'audience_snapshot_count' => $count,
            'queued' => $count,
        ]);

        Log::info('broadcast.sending', ['broadcast_id' => $broadcast->id, 'queued' => $count]);

        SendBroadcastChunkJob::dispatch($broadcast->id)
            ->onQueue(config('telegram.queues.broadcast'));
    }

    public function complete(Broadcast $broadcast): void
    {
        $this->transition($broadcast, BroadcastStatus::Completed, ['finished_at' => now()]);
        $this->snapshot->cleanup($broadcast->id);

        Log::info('broadcast.completed', [
            'broadcast_id' => $broadcast->id,
            'sent' => $broadcast->sent,
            'blocked' => $broadcast->blocked,
            'failed' => $broadcast->failed,
        ]);
    }

    public function fail(Broadcast $broadcast, string $reason): void
    {
        $this->transition($broadcast, BroadcastStatus::Failed, ['finished_at' => now()]);
        $this->snapshot->cleanup($broadcast->id);

        Log::error('broadcast.failed', ['broadcast_id' => $broadcast->id, 'reason' => $reason]);
    }

    // ------------------------------------------------------------------------

    private function transition(Broadcast $broadcast, BroadcastStatus $to, array $extra = []): void
    {
        $from = $broadcast->status;

        if (! in_array($to->value, self::TRANSITIONS[$from->value], true)) {
            throw InvalidBroadcastTransition::between($from, $to);
        }

        $broadcast->forceFill([...$extra, 'status' => $to])->save();
    }
}
