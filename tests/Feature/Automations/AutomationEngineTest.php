<?php

declare(strict_types=1);

use App\Enums\AutomationUserStatus;
use App\Jobs\SendAutomationStepJob;
use App\Models\Automation;
use App\Models\AutomationStep;
use App\Models\AutomationStepTranslation;
use App\Models\AutomationUserState;
use App\Models\User;
use App\Services\Automations\AutomationEnroller;
use App\Services\Automations\AutomationRunner;
use App\Services\Bot\BotLocaleResolver;
use App\Services\Bot\BotMessageSender;
use App\Services\Bot\EmbeddedButtonsRenderer;
use App\Services\Bot\TokenRenderer;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
});

afterEach(fn () => Carbon::setTestNow());

function dripAutomation(array $delays = [24, 48], int $cooldownDays = 0): Automation
{
    $automation = Automation::factory()->create(['cooldown_days' => $cooldownDays]);

    foreach ($delays as $i => $delay) {
        $step = AutomationStep::factory()->for($automation)->create([
            'step_no' => $i,
            'delay_hours' => $delay,
        ]);
        AutomationStepTranslation::factory()->create([
            'automation_step_id' => $step->id,
            'text' => 'Step '.$i.' for {first_name}',
        ]);
    }

    return $automation->load('steps');
}

it('enrolls new users in welcome drips exactly once, delayed from the trigger', function () {
    Carbon::setTestNow('2026-08-17 10:00:00');
    dripAutomation([24, 48]);

    postWebhook(telegramMessageUpdate(11001, '/start'));
    postWebhook(telegramMessageUpdate(11001, '/start'));

    $state = AutomationUserState::query()->first();

    expect(AutomationUserState::query()->count())->toBe(1)
        ->and($state->current_step_no)->toBe(0)
        ->and($state->trigger_key)->toBe('user_joined')
        // Step 0 delay is relative to the trigger; delay > 0 ⇒ never an
        // instant second welcome (spec §4.2).
        ->and($state->next_step_at->toDateTimeString())->toBe('2026-08-18 10:00:00');
});

it('does not enroll in paused or stepless automations', function () {
    Automation::factory()->paused()->create();
    Automation::factory()->create(); // active but has no steps

    postWebhook(telegramMessageUpdate(11001, '/start'));

    expect(AutomationUserState::query()->count())->toBe(0);
});

it('sends the due step and schedules the next one relative to it', function () {
    Carbon::setTestNow('2026-08-17 10:00:00');
    $automation = dripAutomation([1, 48]);
    $user = User::factory()->create();
    $state = AutomationUserState::factory()->create([
        'automation_id' => $automation->id,
        'user_id' => $user->id,
        'current_step_no' => 0,
        'next_step_at' => now()->subMinute(),
    ]);

    $dispatched = app(AutomationRunner::class)->runDue();

    $state->refresh();

    expect($dispatched)->toBe(1)
        ->and(fakeTelegram()->sentTo($user->tg_chat_id))->toHaveCount(1)
        ->and(fakeTelegram()->lastSentTo($user->tg_chat_id)['params']['text'])->toBe('Step 0 for '.$user->first_name)
        ->and($state->current_step_no)->toBe(1)
        // Decision 4: the 48h delay counts from the step just sent.
        ->and($state->next_step_at->toDateTimeString())->toBe('2026-08-19 10:00:00')
        ->and($automation->steps->firstWhere('step_no', 0)->refresh()->sent_count)->toBe(1);
});

it('proves the claim is atomic: a stale concurrent tick can never double-send', function () {
    Queue::fake();
    $automation = dripAutomation([1]);
    $state = AutomationUserState::factory()->create([
        'automation_id' => $automation->id,
        'user_id' => User::factory()->create()->id,
        'current_step_no' => 0,
        'next_step_at' => now()->subMinute(),
    ]);

    // Tick B reads the due row FIRST — this is exactly what a racing
    // scheduler process would hold in memory.
    $staleCurrentStep = $state->current_step_no;
    $staleNextStepAt = $state->next_step_at;

    // Tick A wins: claims and dispatches.
    app(AutomationRunner::class)->runDue();
    Queue::assertPushed(SendAutomationStepJob::class, 1);

    // Tick B replays the IDENTICAL guarded UPDATE with its stale snapshot —
    // the compare-and-swap matches zero rows, so it dispatches nothing.
    $claimedByB = AutomationUserState::query()
        ->whereKey($state->id)
        ->where('status', AutomationUserStatus::Active->value)
        ->where('current_step_no', $staleCurrentStep)
        ->where('next_step_at', $staleNextStepAt)
        ->update(['last_step_sent_at' => now()]);

    expect($claimedByB)->toBe(0);

    // And a full second scheduler pass also finds nothing due.
    app(AutomationRunner::class)->runDue();
    Queue::assertPushed(SendAutomationStepJob::class, 1);
});

it('completes the journey and applies the cooldown', function () {
    Carbon::setTestNow('2026-08-17 10:00:00');
    $automation = dripAutomation([1], cooldownDays: 30);
    $state = AutomationUserState::factory()->create([
        'automation_id' => $automation->id,
        'user_id' => User::factory()->create()->id,
        'current_step_no' => 0,
        'next_step_at' => now()->subMinute(),
    ]);

    app(AutomationRunner::class)->runDue();

    $state->refresh();

    expect($state->status)->toBe(AutomationUserStatus::Cooldown)
        ->and($state->completed_at)->not->toBeNull()
        ->and($state->next_step_at)->toBeNull()
        ->and($state->cooldown_until->toDateTimeString())->toBe('2026-09-16 10:00:00');
});

it('sends nothing while paused and preserves next_step_at for resume', function () {
    $automation = dripAutomation([1]);
    $state = AutomationUserState::factory()->create([
        'automation_id' => $automation->id,
        'user_id' => User::factory()->create()->id,
        'current_step_no' => 0,
        'next_step_at' => now()->subHour(),
    ]);
    $frozenAt = $state->next_step_at;

    $automation->update(['is_active' => false]);

    expect(app(AutomationRunner::class)->runDue())->toBe(0)
        ->and(fakeTelegram()->nothingSent())->toBeTrue()
        ->and($state->refresh()->next_step_at->equalTo($frozenAt))->toBeTrue();

    // Resume: the frozen enrollment continues from where it stopped (spec §4.10).
    $automation->update(['is_active' => true]);

    expect(app(AutomationRunner::class)->runDue())->toBe(1)
        ->and(fakeTelegram()->nothingSent())->toBeFalse();
});

it('skips the send when paused between claim and job execution', function () {
    $automation = dripAutomation([1]);
    $user = User::factory()->create();
    $step = $automation->steps->first();
    $state = AutomationUserState::factory()->create([
        'automation_id' => $automation->id,
        'user_id' => $user->id,
    ]);

    $automation->update(['is_active' => false]);

    (new SendAutomationStepJob($state->id, $step->id, $user->id))->handle(
        app(BotLocaleResolver::class),
        app(TokenRenderer::class),
        app(EmbeddedButtonsRenderer::class),
        app(BotMessageSender::class),
    );

    expect(fakeTelegram()->nothingSent())->toBeTrue()
        ->and($step->refresh()->sent_count)->toBe(0);
});

it('enrolls genuinely inactive users and only them', function () {
    $automation = Automation::factory()->inactiveTrigger(14)->create();
    AutomationStep::factory()->for($automation)->create(['step_no' => 0, 'delay_hours' => 0]);

    $active = User::factory()->create(['last_active_at' => now()->subDay()]);
    $stale = User::factory()->inactiveForDays(20)->create();
    $neverActive = User::factory()->create(['last_active_at' => null, 'joined_at' => now()->subDays(20)]);
    $blocked = User::factory()->blocked()->inactiveForDays(20)->create();

    $enrolled = app(AutomationEnroller::class)->enrollInactive();

    expect($enrolled)->toBe(2)
        ->and(AutomationUserState::query()->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$stale->id, $neverActive->id])->sort()->values()->all());
});

it('blocks re-entry during cooldown and allows it after expiry', function () {
    $automation = Automation::factory()->inactiveTrigger(14)->create();
    AutomationStep::factory()->for($automation)->create(['step_no' => 0, 'delay_hours' => 0]);
    $user = User::factory()->inactiveForDays(30)->create();

    AutomationUserState::factory()->create([
        'automation_id' => $automation->id,
        'user_id' => $user->id,
        'status' => AutomationUserStatus::Cooldown,
        'next_step_at' => null,
        'completed_at' => now()->subDays(5),
        'cooldown_until' => now()->addDays(25),
        'trigger_key' => 'inactive:'.now()->subDays(5)->toDateString(),
    ]);

    expect(app(AutomationEnroller::class)->enrollInactive())->toBe(0);

    // Cooldown expired → a fresh enrollment (new trigger_key) is allowed.
    AutomationUserState::query()->update(['cooldown_until' => now()->subDay()]);

    expect(app(AutomationEnroller::class)->enrollInactive())->toBe(1)
        ->and(AutomationUserState::query()->count())->toBe(2);
});

it('never re-enrolls a user with an active state', function () {
    $automation = Automation::factory()->inactiveTrigger(14)->create();
    AutomationStep::factory()->for($automation)->create(['step_no' => 0, 'delay_hours' => 0]);
    $user = User::factory()->inactiveForDays(30)->create();

    AutomationUserState::factory()->create([
        'automation_id' => $automation->id,
        'user_id' => $user->id,
        'status' => AutomationUserStatus::Active,
    ]);

    expect(app(AutomationEnroller::class)->enrollInactive())->toBe(0);
});

it('localizes steps with english fallback and renders url buttons', function () {
    $automation = dripAutomation([1]);
    $step = $automation->steps->first();
    $step->update(['buttons' => [[
        'kind' => 'url',
        'url' => 'https://sunbet.et',
        'label' => ['en' => 'Play now', 'am' => 'አሁን ይጫወቱ'],
    ]]]);
    AutomationStepTranslation::factory()->amharic()->create([
        'automation_step_id' => $step->id,
        'text' => 'ደረጃ ለ{first_name}',
    ]);

    $amUser = User::factory()->amharic()->create();
    $state = AutomationUserState::factory()->create([
        'automation_id' => $automation->id,
        'user_id' => $amUser->id,
        'next_step_at' => now()->subMinute(),
    ]);

    app(AutomationRunner::class)->runDue();

    $sent = fakeTelegram()->lastSentTo($amUser->tg_chat_id);

    expect($sent['params']['text'])->toBe('ደረጃ ለ'.$amUser->first_name)
        ->and($sent['params']['reply_markup']['inline_keyboard'][0][0]['text'])->toBe('አሁን ይጫወቱ');
});

it('skips blocked users without counting the step', function () {
    $automation = dripAutomation([1]);
    $user = User::factory()->blocked()->create();
    AutomationUserState::factory()->create([
        'automation_id' => $automation->id,
        'user_id' => $user->id,
        'next_step_at' => now()->subMinute(),
    ]);

    app(AutomationRunner::class)->runDue();

    expect(fakeTelegram()->nothingSent())->toBeTrue()
        ->and($automation->steps->first()->refresh()->sent_count)->toBe(0);
});
