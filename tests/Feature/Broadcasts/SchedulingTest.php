<?php

declare(strict_types=1);

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\BroadcastButton;
use App\Models\BroadcastButtonTranslation;
use App\Models\BroadcastTranslation;
use App\Models\User;
use App\Services\Broadcasts\BroadcastLifecycle;
use App\Services\Broadcasts\BroadcastScheduler;
use App\Services\Broadcasts\InvalidBroadcastTransition;
use App\Services\Broadcasts\NextOccurrenceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

it('refuses to schedule in the past', function () {
    $broadcast = Broadcast::factory()->create();

    expect(fn () => app(BroadcastLifecycle::class)->schedule($broadcast, now()->subHour()))
        ->toThrow(InvalidBroadcastTransition::class);
});

it('promotes due scheduled broadcasts and leaves future ones alone', function () {
    User::factory()->create();

    $due = Broadcast::factory()->create(['status' => 'scheduled', 'scheduled_at' => now()->subMinute()]);
    BroadcastTranslation::factory()->for($due)->create();

    $future = Broadcast::factory()->create(['status' => 'scheduled', 'scheduled_at' => now()->addHour()]);

    $result = app(BroadcastScheduler::class)->processDue();

    expect($result['started'])->toBe(1)
        ->and($due->refresh()->status)->toBe(BroadcastStatus::Completed)
        ->and($future->refresh()->status)->toBe(BroadcastStatus::Scheduled);
});

it('materializes a recurring occurrence and re-arms the parent in Addis time', function () {
    // Monday 2026-08-17 12:00 UTC.
    Carbon::setTestNow('2026-08-17 12:00:00');
    User::factory()->count(2)->create();

    $parent = Broadcast::factory()->create([
        'status' => 'scheduled',
        'scheduled_at' => now()->subMinute(),
        'recurrence' => ['frequency' => 'weekly', 'day' => 'saturday', 'time' => '09:00'],
    ]);
    BroadcastTranslation::factory()->for($parent)->create(['text' => 'Weekly odds!']);
    $button = BroadcastButton::factory()->for($parent)->create();
    BroadcastButtonTranslation::factory()->create(['broadcast_button_id' => $button->id]);

    $result = app(BroadcastScheduler::class)->processDue();

    $child = Broadcast::query()->whereKeyNot($parent->id)->first();
    $parent->refresh();

    expect($result['materialized'])->toBe(1)
        ->and($child)->not->toBeNull()
        ->and($child->status)->toBe(BroadcastStatus::Completed)
        ->and($child->recurrence)->toBeNull()
        ->and($child->sent)->toBe(2)
        ->and($child->translations()->count())->toBe(1)
        ->and($child->buttons()->count())->toBe(1)
        // Parent stays a scheduled template with untouched counters…
        ->and($parent->status)->toBe(BroadcastStatus::Scheduled)
        ->and($parent->sent)->toBe(0)
        // …armed for Saturday 09:00 Addis = 06:00 UTC (UTC+3, no DST).
        ->and($parent->scheduled_at->toIso8601String())->toBe('2026-08-22T06:00:00+00:00');
});

it('computes daily, weekly and monthly occurrences in Addis time', function () {
    $calc = app(NextOccurrenceCalculator::class);
    // Monday 2026-08-17 12:00 UTC = 15:00 Addis.
    $after = CarbonImmutable::parse('2026-08-17 12:00:00', 'UTC');

    // Daily 09:00 Addis: today's 09:00 already passed → tomorrow 06:00 UTC.
    expect($calc->next(['frequency' => 'daily', 'time' => '09:00'], $after)->toIso8601String())
        ->toBe('2026-08-18T06:00:00+00:00');

    // Daily 20:00 Addis: still ahead today → today 17:00 UTC.
    expect($calc->next(['frequency' => 'daily', 'time' => '20:00'], $after)->toIso8601String())
        ->toBe('2026-08-17T17:00:00+00:00');

    // Weekly Saturday 09:00 Addis.
    expect($calc->next(['frequency' => 'weekly', 'day' => 'saturday', 'time' => '09:00'], $after)->toIso8601String())
        ->toBe('2026-08-22T06:00:00+00:00');

    // Monthly on the 31st: September clamps to the 30th.
    expect($calc->next(['frequency' => 'monthly', 'day_of_month' => 31, 'time' => '10:00'], CarbonImmutable::parse('2026-09-01 00:00:00', 'UTC'))->toIso8601String())
        ->toBe('2026-09-30T07:00:00+00:00');
});

it('rejects malformed recurrence definitions', function () {
    $calc = app(NextOccurrenceCalculator::class);

    expect(fn () => $calc->next(['frequency' => 'hourly', 'time' => '09:00'], now()))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $calc->next(['frequency' => 'daily', 'time' => '25:99'], now()))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $calc->next(['frequency' => 'weekly', 'day' => 'caturday', 'time' => '09:00'], now()))->toThrow(InvalidArgumentException::class);
});

it('unschedules back to draft', function () {
    $broadcast = Broadcast::factory()->scheduled()->create();

    app(BroadcastLifecycle::class)->unschedule($broadcast);

    expect($broadcast->refresh()->status)->toBe(BroadcastStatus::Draft)
        ->and($broadcast->scheduled_at)->toBeNull();
});
