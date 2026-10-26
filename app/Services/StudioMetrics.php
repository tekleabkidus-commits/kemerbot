<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Broadcast;
use App\Models\TelegramMessage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * True production metrics for the visual Studio cards.
 * Rates and trends are computed from recorded outcomes, never demo numbers.
 */
final class StudioMetrics
{
    public function inbox(): array
    {
        $tz = config('app.display_timezone', 'Africa/Addis_Ababa');
        $today = CarbonImmutable::now($tz)->startOfDay()->utc();
        $start = CarbonImmutable::now($tz)->subDays(30)->startOfDay()->utc();

        $conversations = DB::table('telegram_messages')
            ->where('sent_at', '>=', $start)
            ->selectRaw("user_id, max(case when direction = 'inbound' then sent_at end) as last_inbound, max(case when direction = 'admin_outbound' then sent_at end) as last_reply")
            ->groupBy('user_id')
            ->get()
            ->filter(fn ($row) => $row->last_inbound !== null);

        $replied = $conversations->filter(fn ($row) => $row->last_reply !== null && $row->last_reply >= $row->last_inbound)->count();

        return [
            'active' => User::query()->whereIn('support_status', ['open', 'pending'])->count(),
            'replyRate' => $conversations->count() > 0 ? round($replied / $conversations->count() * 100) : null,
            'reach' => DB::table('broadcast_recipients')->where('status', 'sent')->distinct()->count('user_id'),
            'deliveredToday' => DB::table('broadcast_recipients')
                ->where('status', 'sent')->where('sent_at', '>=', $today)->count()
                + TelegramMessage::query()->where('direction', 'admin_outbound')->where('sent_at', '>=', $today)->count(),
            'replyWindow' => 'Conversations whose latest inbound message received an admin reply in the last 30 days',
        ];
    }

    /** Additive, actual Telegram campaign deliveries, bucketed in Addis time. */
    public function deliveryTrend(int $days = 7): array
    {
        $days = max(1, min(90, $days));
        $tz = config('app.display_timezone', 'Africa/Addis_Ababa');
        $start = CarbonImmutable::now($tz)->subDays($days - 1)->startOfDay();
        $grouped = DB::table('broadcast_recipients')
            ->where('status', 'sent')
            ->where('sent_at', '>=', $start->utc())
            ->selectRaw("(sent_at AT TIME ZONE 'UTC' AT TIME ZONE ?)::date as day, count(*) as total", [$tz])
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $start->addDays($i);

            $series[] = [
                'key' => $day->toDateString(),
                'date' => $day->format('M j'),
                'count' => (int) ($grouped[$day->toDateString()] ?? 0),
            ];
        }

        return $series;
    }

    public function chartPoints(array $values, int $height = 170, int $width = 720, ?int $commonMax = null): string
    {
        $values = array_values(array_map('intval', $values));

        if (count($values) === 0) {
            return '';
        }

        $max = max(1, $commonMax ?? max($values));
        $count = max(1, count($values) - 1);

        return implode(' ', array_map(
            fn ($index, $value) => round($index / $count * $width, 2).','.round($height - min(1, $value / $max) * ($height - 12), 2),
            array_keys($values),
            $values
        ));
    }

    public function sparkline(array $values): string
    {
        if (count($values) < 2) {
            return '0,30 90,30';
        }

        $max = max(1, ...array_map('intval', $values));
        $total = count($values) - 1;

        return implode(' ', array_map(
            fn ($i, $v) => round($i / $total * 92, 1).','.round(42 - ((int) $v / $max) * 31, 1),
            array_keys($values),
            array_values($values)
        ));
    }
}
