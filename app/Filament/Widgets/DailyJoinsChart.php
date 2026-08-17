<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetrics;
use Filament\Widgets\ChartWidget;

class DailyJoinsChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Daily joins (30 days)';

    protected function getData(): array
    {
        $series = app(DashboardMetrics::class)->dailyJoins(30);

        return [
            'datasets' => [[
                'label' => 'Joins',
                'data' => array_values($series),
                // Validated single-series hue (amber-600) — passes light+dark checks.
                'borderColor' => '#d97706',
                'backgroundColor' => 'rgba(217, 119, 6, 0.12)',
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
