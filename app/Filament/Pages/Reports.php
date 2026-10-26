<?php

namespace App\Filament\Pages;

use App\Models\Broadcast;
use App\Models\User;
use App\Services\DashboardMetrics;
use App\Services\StudioMetrics;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class Reports extends Page
{
    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.reports';

    protected static string|\UnitEnum|null $navigationGroup = 'Campaigns';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    public int $period = 30;

    public function setPeriod(int $days): void
    {
        abort_unless(in_array($days, [7, 30, 90], true), 422);
        $this->period = $days;
    }

    protected function getViewData(): array
    {
        $campaigns = Broadcast::query()->where('created_at', '>=', now()->subDays($this->period))->latest()->limit(100)->get();
        $clicks = DB::table('button_clicks')->whereIn('broadcast_id', $campaigns->pluck('id'))->selectRaw('broadcast_id,count(*) as taps,count(distinct user_id) as people')->groupBy('broadcast_id')->get()->keyBy('broadcast_id');
        $conversions = DB::table('conversion_events')->whereIn('broadcast_id', $campaigns->pluck('id'))->selectRaw('broadcast_id,count(*) as total,count(distinct user_id) as people')->groupBy('broadcast_id')->get()->keyBy('broadcast_id');
        $experiments = DB::table('broadcast_recipients as r')->whereIn('r.broadcast_id', $campaigns->filter(fn ($b) => (bool) array_filter($b->experiment['text_b'] ?? [], 'filled') || (int) ($b->experiment['holdout_percent'] ?? 0) > 0)->pluck('id'))
            ->leftJoinSub(DB::table('button_clicks')->select('broadcast_id', 'user_id')->distinct(), 'c', fn ($join) => $join->on('c.broadcast_id', '=', 'r.broadcast_id')->on('c.user_id', '=', 'r.user_id'))
            ->leftJoinSub(DB::table('conversion_events')->select('broadcast_id', 'user_id')->distinct(), 'v', fn ($join) => $join->on('v.broadcast_id', '=', 'r.broadcast_id')->on('v.user_id', '=', 'r.user_id'))
            ->selectRaw("r.broadcast_id,case when r.status='holdout' then 'holdout' else r.variant end as cohort,count(*) as audience,sum(case when r.status='sent' then 1 else 0 end) as sent,count(c.user_id) as clickers,count(v.user_id) as converted")
            ->groupBy('r.broadcast_id', DB::raw("case when r.status='holdout' then 'holdout' else r.variant end"))->get();

        $joins = app(DashboardMetrics::class)->dailyJoins($this->period);
        $studio = app(StudioMetrics::class);
        $deliveries = $studio->deliveryTrend($this->period);
        $sentSeries = array_column($deliveries, 'count');
        $joinSeries = array_values($joins);
        $chartMax = max(1, ...$sentSeries, ...$joinSeries);
        $sendingLine = $studio->chartPoints($sentSeries, 170, 720, $chartMax);
        $joiningLine = $studio->chartPoints($joinSeries, 170, 720, $chartMax);
        $totalUsers = User::query()->count();
        $activeUsers = User::query()->where('last_active_at', '>=', now()->subDays($this->period))->count();
        $sentTotal = (int) $campaigns->sum('sent');
        $clickTotal = (int) $clicks->sum('people');
        $ctr = $sentTotal > 0 ? round(100 * $clickTotal / $sentTotal, 1) : null;

        return compact('campaigns', 'clicks', 'conversions', 'experiments', 'joins', 'deliveries', 'chartMax',
            'sendingLine', 'joiningLine', 'totalUsers', 'activeUsers', 'sentTotal', 'clickTotal', 'ctr');
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('export')->label('Export campaigns')->action(fn () => response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Campaign', 'Status', 'Queued', 'Sent', 'Blocked', 'Failed', 'Skipped']);
            foreach (Broadcast::query()->orderBy('id')->cursor() as $b) {
                fputcsv($out, [preg_match('/^[=+@\-\t\r]/', $b->name ?? '') ? "'".$b->name : ($b->name ?? '#'.$b->id), $b->status->value, $b->queued, $b->sent, $b->blocked, $b->failed, $b->skipped]);
            }fclose($out);
        }, 'kemerbet-campaigns.csv'))];
    }
}
