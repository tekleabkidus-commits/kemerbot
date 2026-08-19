<?php

declare(strict_types=1);

namespace App\Services\Automations;

use App\Enums\AutomationUserStatus;
use App\Models\AutomationUserState;

/**
 * The single place enrollment state moves forward (HARDENING §4). Called
 * only AFTER a step's outcome is safely recorded — never at claim time.
 * Guarded on status=active so a cancelled/completed state never advances.
 */
final class AutomationStateAdvancer
{
    /** Advance past the given step: schedule the next one or finish. */
    public function advancePast(AutomationUserState $state, int $completedStepNo): void
    {
        if ($state->status !== AutomationUserStatus::Active) {
            return;
        }

        $next = $state->automation->steps
            ->filter(fn ($step) => $step->step_no > $completedStepNo)
            ->sortBy('step_no')
            ->first();

        if ($next !== null) {
            // Decision 4: the next delay counts from the step just processed.
            $state->forceFill([
                'current_step_no' => $next->step_no,
                'next_step_at' => now()->addHours($next->delay_hours),
                'last_step_sent_at' => now(),
            ])->save();

            return;
        }

        $this->finish($state, ['last_step_sent_at' => now()]);
    }

    /** Finish the journey: completed, or cooldown when configured. */
    public function finish(AutomationUserState $state, array $extra = []): void
    {
        $cooldownDays = (int) $state->automation->cooldown_days;

        $state->forceFill([
            ...$extra,
            'status' => $cooldownDays > 0
                ? AutomationUserStatus::Cooldown
                : AutomationUserStatus::Completed,
            'next_step_at' => null,
            'completed_at' => now(),
            'cooldown_until' => $cooldownDays > 0 ? now()->addDays($cooldownDays) : null,
        ])->save();
    }

    /** The user became unreachable: stop this enrollment (HARDENING §4). */
    public function cancel(AutomationUserState $state): void
    {
        if ($state->status !== AutomationUserStatus::Active) {
            return;
        }

        $state->forceFill([
            'status' => AutomationUserStatus::Cancelled,
            'next_step_at' => null,
        ])->save();
    }
}
