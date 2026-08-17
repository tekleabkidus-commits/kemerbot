<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Broadcast;
use App\Models\TrackingLink;
use App\Models\User;
use App\Services\DashboardMetrics;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

afterEach(fn () => Carbon::setTestNow());

it('computes the kpi cards', function () {
    User::factory()->count(3)->create(['last_active_at' => now()->subDays(2)]);
    User::factory()->inactiveForDays(40)->create();
    User::factory()->blocked()->inactiveForDays(40)->create();

    $kpis = app(DashboardMetrics::class)->kpis();

    expect($kpis['total'])->toBe(5)
        ->and($kpis['active_7d'])->toBe(3)
        ->and($kpis['active_30d'])->toBe(3)
        ->and($kpis['blocked'])->toBe(1);
});

it('buckets daily joins by Addis days with zero-fill', function () {
    // 22:00 UTC on the 15th = 01:00 on the 16th in Addis (UTC+3).
    Carbon::setTestNow('2026-08-17 12:00:00');
    User::factory()->create(['joined_at' => '2026-08-15 22:00:00']);
    User::factory()->create(['joined_at' => '2026-08-16 08:00:00']);

    $series = app(DashboardMetrics::class)->dailyJoins(5);

    expect($series)->toHaveCount(5)
        ->and($series['2026-08-16'])->toBe(2)
        ->and($series['2026-08-15'])->toBe(0)
        ->and($series['2026-08-17'])->toBe(0);
});

it('serves warm loads entirely from cache', function () {
    User::factory()->create();

    $metrics = app(DashboardMetrics::class);

    expect($metrics->kpis()['total'])->toBe(1);

    User::factory()->create();

    // Still 1 — within the 5-minute cache, no SQL re-runs.
    expect($metrics->kpis()['total'])->toBe(1);

    $metrics->flush();

    expect($metrics->kpis()['total'])->toBe(2);
});

it('summarizes acquisition and broadcast performance', function () {
    TrackingLink::factory()->create(['clicks_count' => 200, 'joins_count' => 50]);
    Broadcast::factory()->create([
        'status' => 'completed',
        'queued' => 100, 'sent' => 90, 'blocked' => 8, 'failed' => 2,
    ]);

    $metrics = app(DashboardMetrics::class);

    expect($metrics->acquisition())->toEqual(['clicks' => 200, 'joins' => 50, 'conversion' => 25.0])
        ->and($metrics->broadcastStats()['sent_rate'])->toBe(90.0)
        ->and($metrics->broadcastStats()['blocked_rate'])->toBe(8.0);
});

it('reports live system health from redis breadcrumbs', function () {
    postWebhook(telegramMessageUpdate(13001, 'hello'));

    $health = app(DashboardMetrics::class)->systemHealth();

    expect($health['last_webhook_at'])->not->toBeNull()
        ->and($health['queue_interactive'])->toBeInt()
        ->and($health['running_broadcasts'])->toBe(0)
        ->and($health['last_api_error'])->toBeNull();

    Redis::set('telegram:last_api_error', json_encode(['method' => 'sendMessage', 'code' => 429, 'description' => 'Too Many Requests', 'at' => now()->toIso8601String()]));

    expect(app(DashboardMetrics::class)->systemHealth()['last_api_error']['code'])->toBe(429);
});

it('renders the dashboard with all widgets for every role', function () {
    $this->seed(SettingsSeeder::class);
    User::factory()->count(2)->create();

    $this->actingAs(Admin::factory()->viewer()->create())
        ->get('/admin')
        ->assertOk();
});
