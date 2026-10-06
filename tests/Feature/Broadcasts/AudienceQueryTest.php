<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Broadcasts\AudienceQuery;

$audience = fn (): AudienceQuery => app(AudienceQuery::class);

it('always excludes blocked users, even for everyone', function () use ($audience) {
    User::factory()->count(3)->create();
    User::factory()->blocked()->count(2)->create();

    expect($audience()->estimatedCount(null))->toBe(3)
        ->and($audience()->estimatedCount(['everyone' => true]))->toBe(3);
});

it('filters by joined after and before', function () use ($audience) {
    User::factory()->create(['joined_at' => '2026-01-15']);
    User::factory()->create(['joined_at' => '2026-06-15']);

    expect($audience()->estimatedCount(['joined_after' => '2026-03-01']))->toBe(1)
        ->and($audience()->estimatedCount(['joined_before' => '2026-03-01']))->toBe(1);
});

it('filters by activity windows', function () use ($audience) {
    User::factory()->create(['last_active_at' => now()->subDays(2)]);
    User::factory()->inactiveForDays(30)->create();
    User::factory()->create(['last_active_at' => null]);

    expect($audience()->estimatedCount(['active_last_days' => 7]))->toBe(1)
        // Inactive: stale OR never active.
        ->and($audience()->estimatedCount(['inactive_days' => 14]))->toBe(2);
});

it('filters by language, source and channel status', function () use ($audience) {
    User::factory()->amharic()->inChannel()->fromSource('tiktok')->create();
    User::factory()->create();

    expect($audience()->estimatedCount(['language' => 'am']))->toBe(1)
        ->and($audience()->estimatedCount(['source' => 'tiktok']))->toBe(1)
        ->and($audience()->estimatedCount(['in_channel' => true]))->toBe(1)
        ->and($audience()->estimatedCount(['in_channel' => false]))->toBe(1);
});

it('combines filters with AND semantics', function () use ($audience) {
    User::factory()->amharic()->fromSource('tiktok')->create();
    User::factory()->amharic()->create();

    expect($audience()->estimatedCount(['language' => 'am', 'source' => 'tiktok']))->toBe(1);
});

it('describes the audience in plain language', function () use ($audience) {
    expect($audience()->describe(null))->toContain('Everyone')
        ->and($audience()->describe(['language' => 'am', 'inactive_days' => 14]))
        ->toContain('for 14+ days')
        ->toContain('language: am')
        ->toContain('blocked and unsubscribed people excluded');
});
