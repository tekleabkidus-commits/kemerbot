<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BroadcastStatus;
use App\Models\Automation;
use App\Models\AutomationStep;
use App\Models\AutomationUserState;
use App\Models\Broadcast;
use App\Models\ButtonClick;
use App\Models\Poll;
use App\Models\TrackingLink;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Every dashboard number flows through here with a 5-minute cache (spec §5.8:
 * no expensive live scans on refresh). A cold load runs ~a dozen aggregate
 * queries, each against an indexed column; warm loads run zero SQL. Day
 * bucketing happens in Addis time (spec §4.1).
 */
final class DashboardMetrics
{
    private const CACHE_SECONDS = 300;

    /** @return array{total: int, new_today: int, active_7d: int, active_30d: int, blocked: int} */
    public function kpis(): array
    {
        return $this->remember('kpis', function (): array {
            $todayStartUtc = now()->timezone(config('app.display_timezone'))->startOfDay()->utc();

            return [
                'total' => User::query()->count(),
                'new_today' => User::query()->where('joined_at', '>=', $todayStartUtc)->count(),
                'active_7d' => User::query()->where('last_active_at', '>=', now()->subDays(7))->count(),
                'active_30d' => User::query()->where('last_active_at', '>=', now()->subDays(30))->count(),
                'blocked' => User::query()->where('blocked_bot', true)->count(),
            ];
        });
    }

    /** @return array<string, int> date (Addis) => joins */
    public function dailyJoins(int $days = 30): array
    {
        return $this->remember("daily_joins:{$days}", fn (): array => $this->dailyCounts('joined_at', $days));
    }

    /** @return array<string, int> date (Addis) => users who blocked the bot */
    public function blockedTrend(int $days = 30): array
    {
        return $this->remember("blocked_trend:{$days}", fn (): array => $this->dailyCounts('blocked_at', $days));
    }

    /** @return array<string, int> source => users (top N, attributed only) */
    public function sourceDistribution(int $top = 10): array
    {
        return $this->remember("sources:{$top}", fn (): array => User::query()
            ->whereNotNull('source')
            ->select('source', DB::raw('count(*) as total'))
            ->groupBy('source')
            ->orderByDesc('total')
            ->limit($top)
            ->pluck('total', 'source')
            ->map(fn ($v) => (int) $v)
            ->all());
    }

    /** @return array{clicks: int, joins: int, conversion: ?float} */
    public function acquisition(): array
    {
        return $this->remember('acquisition', function (): array {
            $totals = TrackingLink::query()
                ->selectRaw('coalesce(sum(clicks_count), 0) as clicks, coalesce(sum(joins_count), 0) as joins')
                ->first();

            $clicks = (int) $totals->clicks;
            $joins = (int) $totals->joins;

            return [
                'clicks' => $clicks,
                'joins' => $joins,
                'conversion' => $clicks > 0 ? round($joins / $clicks * 100, 1) : null,
            ];
        });
    }

    /** @return array{campaigns: int, sent: int, blocked: int, failed: int, clicks: int, sent_rate: ?float, blocked_rate: ?float, failed_rate: ?float, click_rate: ?float} */
    public function broadcastStats(): array
    {
        return $this->remember('broadcast_stats', function (): array {
            $totals = Broadcast::query()
                ->where('status', BroadcastStatus::Completed)
                ->selectRaw('count(*) as campaigns, coalesce(sum(queued),0) as queued, coalesce(sum(sent),0) as sent, coalesce(sum(blocked),0) as blocked, coalesce(sum(failed),0) as failed')
                ->first();

            $queued = (int) $totals->queued;
            $sent = (int) $totals->sent;
            $clicks = ButtonClick::query()->count();

            $rate = fn (int $part): ?float => $queued > 0 ? round($part / $queued * 100, 1) : null;

            return [
                'campaigns' => (int) $totals->campaigns,
                'sent' => $sent,
                'blocked' => (int) $totals->blocked,
                'failed' => (int) $totals->failed,
                'clicks' => $clicks,
                'sent_rate' => $rate($sent),
                'blocked_rate' => $rate((int) $totals->blocked),
                'failed_rate' => $rate((int) $totals->failed),
                'click_rate' => $sent > 0 ? round($clicks / $sent * 100, 1) : null,
            ];
        });
    }

    /** @return array{automations: int, enrollments: int, step_sends: int, completions: int} */
    public function automationStats(): array
    {
        return $this->remember('automation_stats', fn (): array => [
            'automations' => Automation::query()->where('is_active', true)->count(),
            'enrollments' => AutomationUserState::query()->count(),
            'step_sends' => (int) AutomationStep::query()->sum('sent_count'),
            'completions' => AutomationUserState::query()->whereNotNull('completed_at')->count(),
        ]);
    }

    /** @return array{polls: int, votes: int} */
    public function pollStats(): array
    {
        return $this->remember('poll_stats', function (): array {
            $votes = 0;

            foreach (Poll::query()->whereNotNull('answer_counts')->pluck('answer_counts') as $counts) {
                $votes += array_sum(array_map('intval', (array) $counts));
            }

            return [
                'polls' => Poll::query()->count(),
                'votes' => $votes,
            ];
        });
    }

    /**
     * Live (uncached) — a handful of O(1) Redis reads and two indexed counts.
     *
     * @return array{queue_interactive: int, queue_broadcast: int, queue_automation: int, running_broadcasts: int, failed_broadcasts: int, last_webhook_at: ?string, last_api_error: ?array}
     */
    public function systemHealth(): array
    {
        $lastError = Redis::get('telegram:last_api_error');

        return [
            'queue_interactive' => (int) Redis::llen('queues:'.config('telegram.queues.interactive')),
            'queue_broadcast' => (int) Redis::llen('queues:'.config('telegram.queues.broadcast')),
            'queue_automation' => (int) Redis::llen('queues:'.config('telegram.queues.automation')),
            'running_broadcasts' => Broadcast::query()
                ->whereIn('status', [BroadcastStatus::Preparing, BroadcastStatus::Sending])
                ->count(),
            'failed_broadcasts' => Broadcast::query()->where('status', BroadcastStatus::Failed)->count(),
            'last_webhook_at' => Redis::get('telegram:last_webhook_ok_at') ?: null,
            'last_api_error' => $lastError !== null && $lastError !== false
                ? (array) json_decode((string) $lastError, true)
                : null,
        ];
    }

    public function flush(): void
    {
        foreach (['kpis', 'daily_joins:30', 'blocked_trend:30', 'sources:10', 'acquisition', 'broadcast_stats', 'automation_stats', 'poll_stats'] as $key) {
            Cache::forget('dashboard:'.$key);
        }
    }

    /** @return array<string, int> */
    private function dailyCounts(string $column, int $days): array
    {
        $tz = (string) config('app.display_timezone');
        $since = now()->timezone($tz)->subDays($days - 1)->startOfDay()->utc();

        $rows = User::query()
            ->whereNotNull($column)
            ->where($column, '>=', $since)
            ->selectRaw("({$column} AT TIME ZONE 'UTC' AT TIME ZONE ?)::date as day, count(*) as total", [$tz])
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        // Zero-fill so charts show quiet days honestly.
        $series = [];
        $cursor = now()->timezone($tz)->subDays($days - 1)->startOfDay();

        for ($i = 0; $i < $days; $i++) {
            $key = $cursor->toDateString();
            $series[$key] = (int) ($rows[$key] ?? 0);
            $cursor = $cursor->addDay();
        }

        return $series;
    }

    private function remember(string $key, \Closure $callback): mixed
    {
        return Cache::remember('dashboard:'.$key, self::CACHE_SECONDS, $callback);
    }
}
