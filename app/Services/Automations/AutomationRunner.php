<?php

declare(strict_types=1);

namespace App\Services\Automations;

use App\Enums\AutomationDeliveryStatus;
use App\Enums\AutomationUserStatus;
use App\Jobs\SendAutomationStepJob;
use App\Models\AutomationStepDelivery;
use App\Models\AutomationUserState;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Execution side of the automation engine (spec §8), redesigned as a
 * transactional outbox (HARDENING §4):
 *
 *   DB transaction: lock due state (FOR UPDATE SKIP LOCKED)
 *                   → create delivery row (queued)
 *                   → park the state (next_step_at = NULL)
 *   COMMIT, then dispatch the delivery job.
 *
 * The state does NOT advance at claim time — SendAutomationStepJob advances
 * it only after the send outcome is safely recorded. A crash between commit
 * and dispatch leaves a durable queued delivery that recoverStalled()
 * re-dispatches; unique(state, step) makes duplicate claims impossible at
 * the database level. Paused automations (is_active=false) are filtered out
 * before claiming and before recovery re-dispatch: nothing sends, parked
 * state is preserved, resume continues exactly where it stopped (§4.10).
 */
final class AutomationRunner
{
    private const BATCH_LIMIT = 1000;

    public function __construct(private readonly AutomationStateAdvancer $advancer) {}

    /** @return int number of step sends dispatched */
    public function runDue(): int
    {
        $deliveryIds = [];

        DB::transaction(function () use (&$deliveryIds): void {
            $due = AutomationUserState::query()
                ->where('status', AutomationUserStatus::Active)
                ->whereNotNull('next_step_at')
                ->where('next_step_at', '<=', now())
                ->whereHas('automation', fn ($q) => $q->where('is_active', true))
                ->with(['automation.steps'])
                ->orderBy('next_step_at')
                ->limit(self::BATCH_LIMIT)
                ->lock('for update skip locked')
                ->get();

            foreach ($due as $state) {
                $step = $state->automation->steps->firstWhere('step_no', $state->current_step_no);

                if ($step === null) {
                    // Steps were edited out from under the enrollment: finish it.
                    $this->advancer->finish($state);

                    continue;
                }

                try {
                    $delivery = DB::transaction(fn () => AutomationStepDelivery::query()->create([
                        'automation_user_state_id' => $state->id,
                        'automation_step_id' => $step->id,
                        'user_id' => $state->user_id,
                        'status' => AutomationDeliveryStatus::Queued,
                        'queued_at' => now(),
                    ]));
                } catch (QueryException $e) {
                    if ((string) $e->getCode() === '23505') {
                        // unique(state, step): this step was already claimed —
                        // park the state so it stops re-surfacing as due.
                        $state->forceFill(['next_step_at' => null])->save();

                        continue;
                    }

                    throw $e;
                }

                // Park: claimed states leave the due set; advancement happens
                // in the job only after the outcome is recorded.
                $state->forceFill(['next_step_at' => null])->save();

                $deliveryIds[] = $delivery->id;
            }
        });

        // After COMMIT: a crash from here on loses nothing — the queued
        // delivery rows are durable and recoverStalled() re-dispatches them.
        foreach ($deliveryIds as $deliveryId) {
            SendAutomationStepJob::dispatch($deliveryId)
                ->onQueue(config('telegram.queues.automation'));
        }

        if ($deliveryIds !== []) {
            Log::info('automation.steps_dispatched', ['count' => count($deliveryIds)]);
        }

        return count($deliveryIds);
    }

    /**
     * Outbox recovery (HARDENING §4): re-dispatch queued deliveries that have
     * sat idle (lost dispatch / dead worker before claim) and reset `sending`
     * rows whose worker died mid-flight. Job-level claims make an extra
     * dispatch for an already-picked-up delivery a harmless no-op.
     *
     * @return int number of deliveries re-dispatched
     */
    public function recoverStalled(): int
    {
        $stallSeconds = (int) config('telegram.automation_delivery_stall_seconds');

        // Dead mid-send: back to queued so the re-dispatch below picks it up
        // in this same pass (updated_at deliberately left stale).
        AutomationStepDelivery::query()
            ->where('status', AutomationDeliveryStatus::Sending)
            ->where('updated_at', '<', now()->subSeconds($stallSeconds * 2))
            ->update(['status' => AutomationDeliveryStatus::Queued->value, 'updated_at' => DB::raw('updated_at')]);

        $stalled = AutomationStepDelivery::query()
            ->where('status', AutomationDeliveryStatus::Queued)
            ->where('updated_at', '<', now()->subSeconds($stallSeconds))
            ->whereHas('state.automation', fn ($q) => $q->where('is_active', true))
            ->limit(self::BATCH_LIMIT)
            ->get();

        foreach ($stalled as $delivery) {
            // Touch so the next recovery tick doesn't re-dispatch it again
            // before the queue has had a chance to run it.
            $delivery->forceFill(['updated_at' => now()])->save();

            SendAutomationStepJob::dispatch($delivery->id)
                ->onQueue(config('telegram.queues.automation'));
        }

        if ($stalled->isNotEmpty()) {
            Log::warning('automation.deliveries_recovered', ['count' => $stalled->count()]);
        }

        return $stalled->count();
    }
}
