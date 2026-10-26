<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Broadcast;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CampaignListStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total campaigns', number_format(Broadcast::query()->count()))
                ->description('All saved campaigns')
                ->icon('heroicon-o-megaphone'),
            Stat::make('Currently sending', number_format(Broadcast::query()->whereIn('status', ['preparing', 'sending'])->count()))
                ->description('Preparing or delivering'),
            Stat::make('Scheduled', number_format(Broadcast::query()->where('status', 'scheduled')->count()))
                ->description('Waiting for the next send'),
            Stat::make('Completed', number_format(Broadcast::query()->where('status', 'completed')->count()))
                ->description('Finished delivery records'),
        ];
    }
}
