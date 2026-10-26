<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AudienceListStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Telegram audience', number_format(User::query()->count()))
                ->description('Members recorded by the bot'),
            Stat::make('Reachable subscribers', number_format(User::query()->where('marketing_subscribed', true)->where('blocked_bot', false)->count()))
                ->description('Subscribed and not blocked'),
            Stat::make('Active in 7 days', number_format(User::query()->where('last_active_at', '>=', now()->subDays(7))->count()))
                ->description('Recently active people'),
            Stat::make('Open conversations', number_format(User::query()->where('support_status', 'open')->count()))
                ->description('Support attention needed'),
        ];
    }
}
