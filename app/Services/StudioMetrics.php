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

    /** Actual send volume for the last seven Addis calendar days; used for charts. */
    public function deliveryTrend(): array
    {
        $tz = config('app.display_timezone', 'Africa/Addis_Ababa');
        $start = CarbonImmutable::now($tz)->subDays(6)->startOfDay();

        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $local = $start->addDays($i);
            $from = $local->utc();
            $to = $local->addDay()->utc();

            $days[] = [
                'date' => $local->format('D'),
                'count' => DB::table('broadcast_recipients')->where('status', 'sent')->where('sent_at', '>=', $from)->where('sent_at', '<', $to)->count(),
            ];
        }

        return $days;
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
