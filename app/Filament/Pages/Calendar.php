<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Broadcast;
use Carbon\Carbon;
use Filament\Pages\Page;

class Calendar extends Page
{
    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.calendar';

    protected static string|\UnitEnum|null $navigationGroup = 'Campaigns';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    public int $monthOffset = 0;

    public function previousMonth(): void
    {
        $this->monthOffset = max(-12, $this->monthOffset - 1);
    }

    public function nextMonth(): void
    {
        $this->monthOffset = min(24, $this->monthOffset + 1);
    }

    public function currentMonth(): void
    {
        $this->monthOffset = 0;
    }

    protected function getViewData(): array
    {
        $timezone = config('app.display_timezone', 'Africa/Addis_Ababa');
        $month = Carbon::now($timezone)->startOfMonth()->addMonthsNoOverflow($this->monthOffset);
        $firstDay = $month->copy()->startOfWeek(Carbon::SUNDAY);
        $lastDay = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $campaigns = Broadcast::query()
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [
                $firstDay->copy()->startOfDay()->utc(),
                $lastDay->copy()->endOfDay()->utc(),
            ])
            ->orderBy('scheduled_at')
            ->get();

        $byDay = $campaigns->groupBy(
            fn (Broadcast $campaign) => $campaign->scheduled_at->timezone($timezone)->toDateString()
        );

        $weeks = [];

        $offset = 0;

        for ($day = $firstDay->copy(); $day->lte($lastDay); $day->addDay()) {
            $index = intdiv($offset++, 7);
            $weeks[$index][] = [
                'date' => $day->toDateString(),
                'label' => $day->format('j'),
                'isCurrentMonth' => $day->month === $month->month && $day->year === $month->year,
                'isToday' => $day->isToday(),
                'events' => $byDay->get($day->toDateString(), collect()),
            ];
        }

        return [
            'monthLabel' => $month->format('F Y'),
            'weeks' => $weeks,
            'monthEvents' => $campaigns,
            'timezone' => $timezone,
        ];
    }
}
