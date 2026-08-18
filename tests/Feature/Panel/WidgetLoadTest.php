<?php

declare(strict_types=1);

use App\Filament\Widgets\BlockedTrendChart;
use App\Filament\Widgets\BroadcastPerformance;
use App\Filament\Widgets\DailyJoinsChart;
use App\Filament\Widgets\EngagementStats;
use App\Filament\Widgets\KpiOverview;
use App\Filament\Widgets\RecentCampaigns;
use App\Filament\Widgets\SourceDistributionChart;
use App\Filament\Widgets\SystemHealth;
use App\Filament\Widgets\TopReferrers;
use App\Models\Admin;
use App\Models\Broadcast;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Livewire\Livewire;

/**
 * Dashboard widgets lazy-load via Livewire AFTER the page's initial 200, so a
 * page-level smoke test never runs their queries. Mount each widget directly —
 * this is the test that would have caught the Postgres HAVING-alias 500.
 */
it('loads every dashboard widget with real data', function (string $widget) {
    $this->seed(SettingsSeeder::class);

    $referrer = User::factory()->create();
    User::factory()->count(2)->create(['referred_by_user_id' => $referrer->id, 'source' => 'tiktok']);
    User::factory()->blocked()->create();
    Broadcast::factory()->create(['status' => 'completed', 'queued' => 10, 'sent' => 9, 'blocked' => 1]);

    Livewire::actingAs(Admin::factory()->owner()->create())
        ->test($widget)
        ->assertSuccessful();
})->with([
    KpiOverview::class,
    DailyJoinsChart::class,
    BlockedTrendChart::class,
    SourceDistributionChart::class,
    BroadcastPerformance::class,
    EngagementStats::class,
    RecentCampaigns::class,
    TopReferrers::class,
    SystemHealth::class,
]);

it('shows the top referrers ranked with zero-referral users excluded', function () {
    $top = User::factory()->create();
    User::factory()->count(3)->create(['referred_by_user_id' => $top->id]);
    $second = User::factory()->create();
    User::factory()->create(['referred_by_user_id' => $second->id]);
    User::factory()->create(); // no referrals — must not appear

    Livewire::actingAs(Admin::factory()->owner()->create())
        ->test(TopReferrers::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$top, $second])
        ->assertCanNotSeeTableRecords(User::query()->doesntHave('referrals')->get());
});
