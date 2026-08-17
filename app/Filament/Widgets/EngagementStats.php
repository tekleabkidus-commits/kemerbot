<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EngagementStats extends StatsOverviewWidget
{
    protected static ?int $sort = 6;

    protected function getStats(): array
    {
        $metrics = app(DashboardMetrics::class);
        $automations = $metrics->automationStats();
        $polls = $metrics->pollStats();

        return [
            Stat::make('Active automations', number_format($automations['automations']))
                ->description(number_format($automations['enrollments']).' enrollments'),
            Stat::make('Automation steps sent', number_format($automations['step_sends']))
                ->description(number_format($automations['completions']).' journeys completed'),
            Stat::make('Polls', number_format($polls['polls']))
                ->description(number_format($polls['votes']).' votes collected'),
        ];
    }
}
