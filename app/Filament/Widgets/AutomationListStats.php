<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Automation;
use App\Models\AutomationStep;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AutomationListStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Automation journeys', number_format(Automation::query()->count()))
                ->description('All configured journeys'),
            Stat::make('Active journeys', number_format(Automation::query()->where('is_active', true)->count()))
                ->description('Enabled for eligible users'),
            Stat::make('Paused journeys', number_format(Automation::query()->where('is_active', false)->count()))
                ->description('No new enrollments'),
            Stat::make('Journey steps', number_format(AutomationStep::query()->count()))
                ->description('Configured actions and waits'),
        ];
    }
}
