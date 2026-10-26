<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Automation;
use App\Models\Broadcast;
use App\Models\User;
use App\Services\DashboardMetrics;
use App\Services\StudioMetrics;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\DB;

class Home extends Page
{
    protected string $view = 'filament.pages.home';
    protected static ?string $slug = 'home';
    protected static ?string $navigationLabel = 'Home';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?int $navigationSort = -10;

    public int $period = 30;

    public function setPeriod(int $days): void
    {
        abort_unless(in_array($days, [7, 30, 90], true), 422);
        $this->period = $days;
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public function getTitle(): string
    {
        return 'Dashboard';
    }

    protected function getViewData(): array
    {
        $studio = app(StudioMetrics::class);
        $growth = app(DashboardMetrics::class);
        $kpis = $growth->kpis();
        $joins = $growth->dailyJoins($this->period);
        $deliveries = $studio->deliveryTrend($this->period);
        $dailySends = array_column($deliveries, 'count');
        $dailyJoins = array_values($joins);
        $scale = max(1, ...$dailySends, ...$dailyJoins);

        return [
            'kpis' => $kpis,
            'joins' => $joins,
            'deliveries' => $deliveries,
            'sendingLine' => $studio->chartPoints($dailySends, 170, 720, $scale),
            'joiningLine' => $studio->chartPoints($dailyJoins, 170, 720, $scale),
            'studioStats' => $studio->inbox(),
            'sparkline' => $studio->sparkline(array_column($studio->deliveryTrend(), 'count')),
            'campaigns' => Broadcast::query()->latest()->limit(5)->get(),
            'scheduled' => Broadcast::query()->where('status', 'scheduled')->orderBy('scheduled_at')->limit(4)->get(),
            'conversations' => User::query()->whereIn('support_status', ['open', 'pending'])->orderByDesc('last_active_at')->limit(4)->get(),
            'activeJourneys' => Automation::query()->where('is_active', true)->count(),
            'pendingUpdates' => DB::table('telegram_updates')->where('status', '!=', 'processed')->count(),
        ];
    }
}
