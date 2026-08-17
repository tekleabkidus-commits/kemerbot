<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KpiOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $kpis = app(DashboardMetrics::class)->kpis();

        return [
            Stat::make('Total users', number_format($kpis['total'])),
            Stat::make('New today', number_format($kpis['new_today']))
                ->description('Addis time day'),
            Stat::make('Active (7 days)', number_format($kpis['active_7d'])),
            Stat::make('Active (30 days)', number_format($kpis['active_30d'])),
            Stat::make('Blocked the bot', number_format($kpis['blocked']))
                ->description($kpis['total'] > 0 ? round($kpis['blocked'] / $kpis['total'] * 100, 1).'% of audience' : null),
        ];
    }
}
