<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BroadcastPerformance extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected function getStats(): array
    {
        $metrics = app(DashboardMetrics::class);
        $broadcasts = $metrics->broadcastStats();
        $acquisition = $metrics->acquisition();
        $pct = fn (?float $v): string => $v === null ? '—' : $v.'%';

        return [
            Stat::make('Broadcasts delivered', $pct($broadcasts['sent_rate']))
                ->description(number_format($broadcasts['sent']).' sent across '.$broadcasts['campaigns'].' campaigns'),
            Stat::make('Blocked rate', $pct($broadcasts['blocked_rate']))
                ->description(number_format($broadcasts['blocked']).' blocked sends'),
            Stat::make('Click-through', $pct($broadcasts['click_rate']))
                ->description(number_format($broadcasts['clicks']).' button clicks'),
            Stat::make('Link conversion', $acquisition['conversion'] === null ? '—' : $acquisition['conversion'].'%')
                ->description(number_format($acquisition['clicks']).' clicks → '.number_format($acquisition['joins']).' joins'),
        ];
    }
}
