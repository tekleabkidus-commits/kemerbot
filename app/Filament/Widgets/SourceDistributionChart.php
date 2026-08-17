<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetrics;
use Filament\Widgets\ChartWidget;

class SourceDistributionChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Acquisition sources (top 10)';

    protected function getData(): array
    {
        $sources = app(DashboardMetrics::class)->sourceDistribution(10);

        return [
            'datasets' => [[
                'label' => 'Users',
                'data' => array_values($sources),
                // Magnitude across categories → one hue, not a rainbow.
                'backgroundColor' => '#d97706',
                'borderRadius' => 4,
                'maxBarThickness' => 24,
            ]],
            'labels' => array_keys($sources),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
