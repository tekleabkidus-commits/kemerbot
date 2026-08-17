<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetrics;
use Filament\Widgets\ChartWidget;

class BlockedTrendChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Users blocking the bot (30 days)';

    protected function getData(): array
    {
        $series = app(DashboardMetrics::class)->blockedTrend(30);

        return [
            'datasets' => [[
                'label' => 'Blocked',
                'data' => array_values($series),
                // Validated single-series hue (indigo-600) — passes light+dark checks.
                'borderColor' => '#4f46e5',
                'backgroundColor' => 'rgba(79, 70, 229, 0.12)',
                'borderWidth' => 2,
                'pointRadius' => 0,
                'pointHitRadius' => 12,
                'fill' => true,
                'tension' => 0.25,
            ]],
            'labels' => array_keys($series),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
