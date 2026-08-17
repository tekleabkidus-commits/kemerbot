<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/** Spec §13: marketers see health signals, never stack traces. */
class SystemHealth extends StatsOverviewWidget
{
    protected static ?int $sort = 9;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $health = app(DashboardMetrics::class)->systemHealth();

        $queueTotal = $health['queue_interactive'] + $health['queue_broadcast'] + $health['queue_automation'];

        $lastWebhook = $health['last_webhook_at'] !== null
            ? Carbon::parse($health['last_webhook_at'])->diffForHumans()
            : 'never';

        $lastError = $health['last_api_error'];

        return [
            Stat::make('Queued jobs', number_format($queueTotal))
                ->description("interactive {$health['queue_interactive']} · broadcast {$health['queue_broadcast']} · automation {$health['queue_automation']}")
                ->color($queueTotal > 5000 ? 'warning' : 'success'),
            Stat::make('Broadcasts running', (string) $health['running_broadcasts'])
                ->description($health['failed_broadcasts'] > 0 ? "{$health['failed_broadcasts']} failed — see Broadcasts" : 'no failures')
                ->color($health['failed_broadcasts'] > 0 ? 'danger' : 'success'),
            Stat::make('Last webhook', $lastWebhook)
                ->color($health['last_webhook_at'] === null ? 'warning' : 'success'),
            Stat::make('Last Telegram error', $lastError === null ? 'none recorded' : ($lastError['code'] ?? 'network'))
                ->description($lastError !== null
                    ? mb_substr((string) ($lastError['description'] ?? ''), 0, 60).' · '.Carbon::parse($lastError['at'])->diffForHumans()
                    : null)
                ->color($lastError === null ? 'success' : 'warning'),
        ];
    }
}
