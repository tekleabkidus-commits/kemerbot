<?php

namespace App\Filament\Pages;

use App\Models\Broadcast;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class Reports extends Page
{
    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.reports';

    protected static string|\UnitEnum|null $navigationGroup = 'Campaigns';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected function getViewData(): array
    {
        $campaigns = Broadcast::latest()->limit(30)->get();
        $clicks = DB::table('button_clicks')->whereIn('broadcast_id', $campaigns->pluck('id'))->selectRaw('broadcast_id,count(*) as taps,count(distinct user_id) as people')->groupBy('broadcast_id')->get()->keyBy('broadcast_id');
        $conversions = DB::table('conversion_events')->whereIn('broadcast_id', $campaigns->pluck('id'))->selectRaw('broadcast_id,count(*) as total,count(distinct user_id) as people')->groupBy('broadcast_id')->get()->keyBy('broadcast_id');
        $experiments = DB::table('broadcast_recipients as r')->whereIn('r.broadcast_id', $campaigns->filter(fn ($b) => (bool) array_filter($b->experiment['text_b'] ?? [], 'filled') || (int) ($b->experiment['holdout_percent'] ?? 0) > 0)->pluck('id'))
            ->leftJoinSub(DB::table('button_clicks')->select('broadcast_id', 'user_id')->distinct(), 'c', fn ($join) => $join->on('c.broadcast_id', '=', 'r.broadcast_id')->on('c.user_id', '=', 'r.user_id'))
            ->leftJoinSub(DB::table('conversion_events')->select('broadcast_id', 'user_id')->distinct(), 'v', fn ($join) => $join->on('v.broadcast_id', '=', 'r.broadcast_id')->on('v.user_id', '=', 'r.user_id'))
            ->selectRaw("r.broadcast_id,case when r.status='holdout' then 'holdout' else r.variant end as cohort,count(*) as audience,sum(case when r.status='sent' then 1 else 0 end) as sent,count(c.user_id) as clickers,count(v.user_id) as converted")
            ->groupBy('r.broadcast_id', DB::raw("case when r.status='holdout' then 'holdout' else r.variant end"))->get();

        return compact('campaigns', 'clicks', 'conversions', 'experiments');
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
