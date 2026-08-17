<?php

declare(strict_types=1);

namespace App\Services\Automations;

use App\Enums\AutomationTrigger;
use App\Enums\AutomationUserStatus;
use App\Models\Automation;
use App\Models\AutomationUserState;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Enrollment side of THE one automation engine (Principle 1, spec §8).
 * unique(automation_id, user_id, trigger_key) makes every path idempotent.
 */
final class AutomationEnroller
{
    /**
     * user_joined trigger: fired once per new user. trigger_key is constant,
     * so a user can never re-enter a welcome drip — even via repeated /start.
     */
    public function enrollUserJoined(User $user): int
    {
        $enrolled = 0;

        $automations = Automation::query()
            ->where('trigger', AutomationTrigger::UserJoined)
            ->where('is_active', true)
            ->with('steps')
            ->get();

        foreach ($automations as $automation) {
            if ($this->enroll($automation, $user, 'user_joined')) {
                $enrolled++;
            }
        }

        return $enrolled;
    }

    /**
     * inactive trigger (scheduler pass): users whose last_active_at is stale
     * (or null, judged by joined_at) and who have no active enrollment and no
     * unexpired cooldown for the automation. The date-scoped trigger_key plus
     * an at-least-one-day effective cooldown prevent daily re-spam.
     */
    public function enrollInactive(): int
    {
        $enrolled = 0;

        $automations = Automation::query()
            ->where('trigger', AutomationTrigger::Inactive)
            ->where('is_active', true)
            ->with('steps')
            ->get();

        foreach ($automations as $automation) {
            $days = max(1, (int) ($automation->trigger_config['inactive_days'] ?? 14));
            $cutoff = now()->subDays($days);

            $candidates = User::query()
                ->where('blocked_bot', false)
                ->where(function (Builder $q) use ($cutoff) {
                    $q->where('last_active_at', '<=', $cutoff)
                        ->orWhere(function (Builder $q) use ($cutoff) {
                            $q->whereNull('last_active_at')->where('joined_at', '<=', $cutoff);
                        });
                })
                ->whereNotExists(function ($q) use ($automation) {
                    $q->selectRaw('1')
                        ->from('automation_user_states')
                        ->whereColumn('automation_user_states.user_id', 'users.id')
                        ->where('automation_user_states.automation_id', $automation->id)
                        ->where(function ($q) {
                            $q->where('status', AutomationUserStatus::Active->value)
                                ->orWhere('cooldown_until', '>', now());
                        });
                })
                ->cursor();

            foreach ($candidates as $user) {
                if ($this->enroll($automation, $user, 'inactive:'.now()->toDateString())) {
                    $enrolled++;
                }
            }
        }

        return $enrolled;
    }

    private function enroll(Automation $automation, User $user, string $triggerKey): bool
    {
        $firstStep = $automation->steps->first();

        if ($firstStep === null) {
            return false;
        }

        // Step 0's delay is relative to the trigger time (spec §4.4). Welcome
        // drips require delay > 0 (spec §4.2) — enforced in admin validation.
        $state = AutomationUserState::query()->firstOrCreate(
            [
                'automation_id' => $automation->id,
                'user_id' => $user->id,
                'trigger_key' => $triggerKey,
            ],
            [
                'current_step_no' => $firstStep->step_no,
                'status' => AutomationUserStatus::Active,
                'triggered_at' => now(),
                'next_step_at' => now()->addHours($firstStep->delay_hours),
            ],
        );

        if ($state->wasRecentlyCreated) {
            Log::info('automation.enrolled', [
                'automation_id' => $automation->id,
                'user_id' => $user->id,
                'trigger_key' => $triggerKey,
            ]);

            return true;
        }

        return false;
    }
}
