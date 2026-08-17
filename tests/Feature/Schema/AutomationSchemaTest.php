<?php

declare(strict_types=1);

use App\Enums\AutomationTrigger;
use App\Enums\AutomationUserStatus;
use App\Models\Automation;
use App\Models\AutomationStep;
use App\Models\AutomationStepTranslation;
use App\Models\AutomationUserState;
use App\Models\User;
use Illuminate\Database\QueryException;

it('casts trigger config and enums', function () {
    $automation = Automation::factory()->inactiveTrigger(21)->create();

    $automation->refresh();

    expect($automation->trigger)->toBe(AutomationTrigger::Inactive)
        ->and($automation->trigger_config)->toBe(['inactive_days' => 21])
        ->and($automation->cooldown_days)->toBe(30);
});

it('orders steps by step_no', function () {
    $automation = Automation::factory()->create();
    AutomationStep::factory()->for($automation)->create(['step_no' => 1, 'delay_hours' => 48]);
    AutomationStep::factory()->for($automation)->create(['step_no' => 0, 'delay_hours' => 2]);

    expect($automation->steps->pluck('step_no')->all())->toBe([0, 1]);
});

it('rejects duplicate step numbers within one automation', function () {
    $automation = Automation::factory()->create();
    AutomationStep::factory()->for($automation)->create(['step_no' => 0]);

    expect(fn () => AutomationStep::factory()->for($automation)->create(['step_no' => 0]))
        ->toThrow(QueryException::class);
});

it('cascades steps, translations and user states when an automation is deleted', function () {
    $automation = Automation::factory()->create();
    $step = AutomationStep::factory()->for($automation)->create();
    AutomationStepTranslation::factory()->create(['automation_step_id' => $step->id]);
    AutomationUserState::factory()->for($automation)->create();

    $automation->delete();

    expect(AutomationStep::query()->count())->toBe(0)
        ->and(AutomationStepTranslation::query()->count())->toBe(0)
        ->and(AutomationUserState::query()->count())->toBe(0);
});

it('tracks per-user execution state with status transitions', function () {
    $state = AutomationUserState::factory()->cooldown()->create();

    $state->refresh();

    expect($state->status)->toBe(AutomationUserStatus::Cooldown)
        ->and($state->cooldown_until)->not->toBeNull()
        ->and($state->completed_at)->not->toBeNull();
});

it('prevents duplicate enrollment for the same trigger key', function () {
    $automation = Automation::factory()->create();
    $user = User::factory()->create();

    AutomationUserState::factory()->create([
        'automation_id' => $automation->id,
        'user_id' => $user->id,
        'trigger_key' => 'joined:2026-08-17',
    ]);

    expect(fn () => AutomationUserState::factory()->create([
        'automation_id' => $automation->id,
        'user_id' => $user->id,
        'trigger_key' => 'joined:2026-08-17',
    ]))->toThrow(QueryException::class);
});

it('allows re-enrollment with a different trigger key', function () {
    $automation = Automation::factory()->create();
    $user = User::factory()->create();

    AutomationUserState::factory()->completed()->create([
        'automation_id' => $automation->id,
        'user_id' => $user->id,
        'trigger_key' => 'inactive:2026-07-01',
    ]);
    AutomationUserState::factory()->create([
        'automation_id' => $automation->id,
        'user_id' => $user->id,
        'trigger_key' => 'inactive:2026-08-15',
    ]);

    expect(AutomationUserState::query()->count())->toBe(2);
});
