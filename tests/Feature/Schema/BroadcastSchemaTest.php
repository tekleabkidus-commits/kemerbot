<?php

declare(strict_types=1);

use App\Enums\BroadcastStatus;
use App\Enums\BroadcastType;
use App\Enums\FailureCategory;
use App\Models\Admin;
use App\Models\Broadcast;
use App\Models\BroadcastButton;
use App\Models\BroadcastButtonTranslation;
use App\Models\BroadcastFailure;
use App\Models\BroadcastTranslation;
use App\Models\User;
use Illuminate\Database\QueryException;

it('defaults to a draft standard broadcast with zeroed counters', function () {
    $broadcast = Broadcast::factory()->create();

    $broadcast->refresh();

    expect($broadcast->type)->toBe(BroadcastType::Standard)
        ->and($broadcast->status)->toBe(BroadcastStatus::Draft)
        ->and($broadcast->queued)->toBe(0)
        ->and($broadcast->sent)->toBe(0)
        ->and($broadcast->blocked)->toBe(0)
        ->and($broadcast->failed)->toBe(0);
});

it('round-trips jsonb fields', function () {
    $broadcast = Broadcast::factory()->recurring()->matchCard()->create([
        'audience_filter' => ['language' => 'am', 'active_last_days' => 7],
    ]);

    $broadcast->refresh();

    // toEqual, not toBe: Postgres jsonb does not preserve object key order.
    expect($broadcast->audience_filter)->toEqual(['language' => 'am', 'active_last_days' => 7])
        ->and($broadcast->recurrence['frequency'])->toBe('weekly')
        ->and($broadcast->template_fields['home_team'])->toBe('St. George');
});

it('identifies terminal statuses', function () {
    expect(BroadcastStatus::Completed->isTerminal())->toBeTrue()
        ->and(BroadcastStatus::Cancelled->isTerminal())->toBeTrue()
        ->and(BroadcastStatus::Failed->isTerminal())->toBeTrue()
        ->and(BroadcastStatus::Sending->isTerminal())->toBeFalse()
        ->and(BroadcastStatus::Paused->isTerminal())->toBeFalse();
});

it('keeps the broadcast but nulls created_by when the creating admin is deleted', function () {
    $admin = Admin::factory()->create();
    $broadcast = Broadcast::factory()->create(['created_by' => $admin->id]);

    $admin->delete();

    expect($broadcast->refresh()->created_by)->toBeNull();
});

it('cascades translations, buttons, button translations and failures on delete', function () {
    $broadcast = Broadcast::factory()->create();
    BroadcastTranslation::factory()->for($broadcast)->create();
    $button = BroadcastButton::factory()->for($broadcast)->create();
    BroadcastButtonTranslation::factory()->create(['broadcast_button_id' => $button->id]);
    BroadcastFailure::factory()->for($broadcast)->create();

    $broadcast->delete();

    expect(BroadcastTranslation::query()->count())->toBe(0)
        ->and(BroadcastButton::query()->count())->toBe(0)
        ->and(BroadcastButtonTranslation::query()->count())->toBe(0)
        ->and(BroadcastFailure::query()->count())->toBe(0);
});

it('records one failure row per user per broadcast', function () {
    $broadcast = Broadcast::factory()->create();
    $user = User::factory()->create();
    BroadcastFailure::factory()->blocked()->create([
        'broadcast_id' => $broadcast->id,
        'user_id' => $user->id,
    ]);

    expect($broadcast->failures()->first()->category)->toBe(FailureCategory::Blocked);

    expect(fn () => BroadcastFailure::factory()->create([
        'broadcast_id' => $broadcast->id,
        'user_id' => $user->id,
    ]))->toThrow(QueryException::class);
});

it('rejects negative counters via check constraint', function () {
    $broadcast = Broadcast::factory()->create();

    expect(fn () => $broadcast->update(['sent' => -1]))
        ->toThrow(QueryException::class);
});
