<?php

declare(strict_types=1);

namespace App\Services\Broadcasts;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Computes the next run of a recurrence definition. Definitions speak Addis
 * time ("every Saturday 09:00" = 09:00 Africa/Addis_Ababa, spec §4.1);
 * results are returned in UTC for storage.
 *
 * Shape: {frequency: daily|weekly|monthly, time: "HH:MM",
 *         day: monday..sunday (weekly), day_of_month: 1-31 (monthly)}
 */
final class NextOccurrenceCalculator
{
    public function next(array $recurrence, CarbonInterface $after): CarbonImmutable
    {
        $tz = (string) config('app.display_timezone');
        $frequency = $recurrence['frequency'] ?? null;
        [$hour, $minute] = $this->parseTime((string) ($recurrence['time'] ?? ''));

        $local = CarbonImmutable::parse($after)->timezone($tz);

        $candidate = match ($frequency) {
            'daily' => $this->nextDaily($local, $hour, $minute),
            'weekly' => $this->nextWeekly($local, (string) ($recurrence['day'] ?? ''), $hour, $minute),
            'monthly' => $this->nextMonthly($local, (int) ($recurrence['day_of_month'] ?? 0), $hour, $minute),
            default => throw new InvalidArgumentException('Unknown recurrence frequency: '.var_export($frequency, true)),
        };

        return $candidate->utc();
    }

    private function nextDaily(CarbonImmutable $local, int $hour, int $minute): CarbonImmutable
    {
        $today = $local->setTime($hour, $minute);

        return $today->isAfter($local) ? $today : $today->addDay();
    }

    private function nextWeekly(CarbonImmutable $local, string $day, int $hour, int $minute): CarbonImmutable
    {
        $dayNumber = match (strtolower($day)) {
            'monday' => CarbonInterface::MONDAY,
            'tuesday' => CarbonInterface::TUESDAY,
            'wednesday' => CarbonInterface::WEDNESDAY,
            'thursday' => CarbonInterface::THURSDAY,
            'friday' => CarbonInterface::FRIDAY,
            'saturday' => CarbonInterface::SATURDAY,
            'sunday' => CarbonInterface::SUNDAY,
            default => throw new InvalidArgumentException("Unknown recurrence day: {$day}"),
        };

        $candidate = $local->setTime($hour, $minute);

        while ($candidate->dayOfWeek !== $dayNumber || ! $candidate->isAfter($local)) {
            $candidate = $candidate->addDay();
        }

        return $candidate;
    }

    private function nextMonthly(CarbonImmutable $local, int $dayOfMonth, int $hour, int $minute): CarbonImmutable
    {
        if ($dayOfMonth < 1 || $dayOfMonth > 31) {
            throw new InvalidArgumentException("Invalid day_of_month: {$dayOfMonth}");
        }

        $candidate = $this->monthlyCandidate($local, $dayOfMonth, $hour, $minute);

        if (! $candidate->isAfter($local)) {
            $candidate = $this->monthlyCandidate($local->addMonthNoOverflow()->startOfMonth(), $dayOfMonth, $hour, $minute);
        }

        return $candidate;
    }

    private function monthlyCandidate(CarbonImmutable $inMonth, int $dayOfMonth, int $hour, int $minute): CarbonImmutable
    {
        // Feb 30 → Feb 28/29: clamp to the month's last day rather than skipping.
        $day = min($dayOfMonth, $inMonth->daysInMonth);

        return $inMonth->setDay($day)->setTime($hour, $minute);
    }

    /** @return array{0: int, 1: int} */
    private function parseTime(string $time): array
    {
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $m) !== 1) {
            throw new InvalidArgumentException("Invalid recurrence time: {$time}");
        }

        return [(int) $m[1], (int) $m[2]];
    }
}
