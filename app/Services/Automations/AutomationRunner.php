<?php

declare(strict_types=1);

namespace App\Services\Automations;

use App\Enums\AutomationUserStatus;
use App\Jobs\SendAutomationStepJob;
use App\Models\AutomationUserState;
use Illuminate\Support\Facades\Log;

/**
 * Execution side of the automation engine (spec §8). Each due row is claimed
 * with a single compare-and-swap UPDATE that advances the state in the same
 * statement — two concurrent scheduler ticks can both READ a due row, but
 * only one can WIN the CAS (guarded on id + status + current_step_no +
 * next_step_at), so a step is never dispatched twice. Paused automations
 * (is_active=false) are filtered out before claiming: nothing sends, and
 * next_step_at is preserved so resume continues exactly where it stopped
 * (spec §4.10). State lives in Postgres; worker restarts lose nothing.
 */
final class AutomationRunner
{
    private const BATCH_LIMIT = 1000;

    /** @return int number of step sends dispatched */
    public function runDue(): int
    {
        $dispatched = 0;

        $due = AutomationUserState::query()
            ->where('status', AutomationUserStatus::Active)
            ->whereNotNull('next_step_at')
            ->where('next_step_at', '<=', now())
            ->whereHas('automation', fn ($q) => $q->where('is_active', true))
            ->with(['automation.steps'])
            ->orderBy('next_step_at')
            ->limit(self::BATCH_LIMIT)
            ->get();

        foreach ($due as $state) {
            $step = $state->automation->steps->firstWhere('step_no', $state->current_step_no);

            if ($step === null) {
                // Steps were edited out from under the enrollment: finish it.
                $this->claim($state, $this->completionAttributes($state));

                continue;
            }

            $next = $state->automation->steps
                ->filter(fn ($s) => $s->step_no > $state->current_step_no)
                ->sortBy('step_no')
                ->first();

            // Decision 4: the next delay counts from the previous (this) step.
            $attributes = $next !== null
                ? [
                    'current_step_no' => $next->step_no,
                    'next_step_at' => now()->addHours($next->delay_hours),
                    'last_step_sent_at' => now(),
                ]
                : [...$this->completionAttributes($state), 'last_step_sent_at' => now()];

            if (! $this->claim($state, $attributes)) {
                continue; // Another tick won the CAS — no double-send.
            }

            SendAutomationStepJob::dispatch($state->id, $step->id, $state->user_id)
                ->onQueue(config('telegram.queues.automation'));

            $dispatched++;
        }

        if ($dispatched > 0) {
            Log::info('automation.steps_dispatched', ['count' => $dispatched]);
        }

        return $dispatched;
    }

    /**
     * Atomic claim: one UPDATE, matched on the exact observed state. Returns
     * false when a concurrent tick already advanced the row.
     */
    private function claim(AutomationUserState $state, array $attributes): bool
    {
        return AutomationUserState::query()
            ->whereKey($state->id)
            ->where('status', AutomationUserStatus::Active->value)
            ->where('current_step_no', $state->current_step_no)
            ->where('next_step_at', $state->next_step_at)
            ->update($attributes) === 1;
    }

    private function completionAttributes(AutomationUserState $state): array
    {
        $cooldownDays = (int) $state->automation->cooldown_days;

        return [
            'status' => $cooldownDays > 0
                ? AutomationUserStatus::Cooldown->value
                : AutomationUserStatus::Completed->value,
            'next_step_at' => null,
            'completed_at' => now(),
            'cooldown_until' => $cooldownDays > 0 ? now()->addDays($cooldownDays) : null,
        ];
    }
}
