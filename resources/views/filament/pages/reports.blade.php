<x-filament-panels::page>
    @php
        $sentTotal = $campaigns->sum('sent');
        $queuedTotal = $campaigns->sum('queued');
        $clickTotal = $clicks->sum('taps');
        $uniqueClicks = $clicks->sum('people');
        $conversionTotal = $conversions->sum('total');
        $maxSent = max(1, (int) $campaigns->max('sent'));
    @endphp
    <div class="ks-page">
        <header class="ks-page-head">
            <div><span class="ks-eyebrow">PERFORMANCE & ANALYTICS</span><h2 class="ks-heading">Understand your impact.</h2><p class="ks-subtitle">Delivery, engagement and conversion results from your 30 most recent campaigns.</p></div>
            <div class="ks-page-actions"><a class="ks-action" href="{{ \App\Filament\Resources\Broadcasts\BroadcastResource::getUrl() }}">All campaigns</a></div>
        </header>
        <section class="ks-stats-grid" aria-label="Campaign performance overview">
            @foreach([
                ['Messages sent', $sentTotal, 'Across recent campaigns', '↗'],
                ['Unique clickers', $uniqueClicks, 'Summed across campaigns', '◉'],
                ['Button taps', $clickTotal, 'All tracked interactions', '⌁'],
                ['Conversions', $conversionTotal, 'Reported through integrations', '✓']
            ] as [$label,$value,$detail,$icon])
                <article class="ks-stat"><div class="ks-stat-head"><span class="ks-stat-icon">{{ $icon }}</span><span>{{ $label }}</span></div><strong class="ks-stat-number">{{ number_format($value) }}</strong><span class="ks-stat-detail">{{ $detail }}</span></article>
            @endforeach
        </section>
        <div class="ks-grid">
            <section class="ks-panel ks-panel-pad ks-span-8">
                <div class="ks-panel-header"><div><h3>Campaign delivery</h3><p>Sent volume for your most recent campaigns</p></div><span class="ks-chip">{{ $campaigns->count() }} campaigns</span></div>
                @forelse($campaigns->take(8) as $campaign)
                    <div class="ks-list-row">
                        <span class="ks-list-avatar" aria-hidden="true">↗</span>
                        <span class="ks-list-content"><strong>{{ $campaign->name ?: 'Campaign #'.$campaign->id }}</strong><small>{{ ucfirst($campaign->status->value) }} · {{ number_format($campaign->queued) }} queued</small></span>
                        <div style="flex:0 1 34%;min-width:75px">
                            <div class="ks-progress" role="progressbar" aria-label="{{ $campaign->name ?: 'Campaign #'.$campaign->id }} sent volume" aria-valuenow="{{ $campaign->sent }}" aria-valuemin="0" aria-valuemax="{{ $maxSent }}"><span style="width:{{ min(100, 100 * $campaign->sent / $maxSent) }}%"></span></div>
                        </div>
                        <strong style="font-size:12px;min-width:54px;text-align:right">{{ number_format($campaign->sent) }}</strong>
                    </div>
                @empty
                    <div class="ks-empty"><span class="symbol">▥</span><h4>No delivery data yet</h4><p>Send a campaign to populate this report.</p></div>
                @endforelse
            </section>
            <section class="ks-panel ks-panel-pad ks-span-4">
                <div class="ks-panel-header"><div><h3>Engagement snapshot</h3><p>Within the selected recent campaigns</p></div></div>
                <div style="display:grid;gap:13px">
                    <div class="ks-insight"><strong>{{ $sentTotal ? number_format(100 * $uniqueClicks / $sentTotal, 1) : '0.0' }}% · unique-clicker ratio</strong>Unique clickers divided by messages sent. People may appear in multiple campaigns.</div>
                    <div class="ks-insight" style="background:#f8f7fd;border-color:#e6e1f5;color:#5f5480"><strong style="color:#69539b">{{ $clickTotal ? number_format(100 * $conversionTotal / $clickTotal, 1) : '0.0' }}% · conversion-to-tap ratio</strong>Reported conversions compared with tracked taps. Different events may be counted more than once.</div>
                    <p style="font-size:11px;color:#7e9083;line-height:1.65">Conversion events require your business system to submit authenticated, verified records. These metrics never infer sales or revenue.</p>
                </div>
            </section>
        </div>
        <section class="ks-panel ks-panel-pad">
            <div class="ks-panel-header"><div><h3>Campaign performance</h3><p>Delivery and engagement details · newest first</p></div></div>
            <div class="ks-table-scroll">
                <table class="ks-table">
                    <thead><tr><th>Campaign</th><th>Status</th><th class="num">Queued</th><th class="num">Sent</th><th class="num">Unique clickers</th><th class="num">Total taps</th><th class="num">Conversions</th></tr></thead>
                    <tbody>
                    @forelse($campaigns as $campaign)
                        <tr>
                            <td><strong>{{ $campaign->name ?: 'Campaign #'.$campaign->id }}</strong></td>
                            <td><span class="ks-chip {{ in_array($campaign->status->value, ['sending','preparing','scheduled']) ? 'amber' : (in_array($campaign->status->value,['cancelled','failed']) ? 'rose' : '') }}">{{ ucfirst($campaign->status->value) }}</span></td>
                            <td class="num">{{ number_format($campaign->queued) }}</td>
                            <td class="num">{{ number_format($campaign->sent) }}</td>
                            <td class="num">{{ number_format($clicks[$campaign->id]->people ?? 0) }}</td>
                            <td class="num">{{ number_format($clicks[$campaign->id]->taps ?? 0) }}</td>
                            <td class="num">{{ number_format($conversions[$campaign->id]->total ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Campaign performance will appear after the first broadcast.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <section class="ks-panel ks-panel-pad">
            <div class="ks-panel-header"><div><h3>Experiments & holdouts</h3><p>Compare actual audience cohorts, conversions and engagement</p></div></div>
            <div class="ks-table-scroll">
                <table class="ks-table">
                    <thead><tr><th>Campaign</th><th>Variant</th><th class="num">Audience</th><th class="num">Sent</th><th class="num">Clickers</th><th class="num">Converted</th><th class="num">Conversion rate</th></tr></thead>
                    <tbody>
                    @forelse($experiments as $experiment)
                        <tr>
                            <td>#{{ $experiment->broadcast_id }}</td><td><span class="ks-chip slate">{{ strtoupper($experiment->cohort) }}</span></td><td class="num">{{ number_format($experiment->audience) }}</td><td class="num">{{ number_format($experiment->sent) }}</td>
                            <td class="num">{{ number_format($experiment->clickers) }}</td><td class="num">{{ number_format($experiment->converted) }}</td>
                            <td class="num">{{ $experiment->audience ? number_format(100 * $experiment->converted / $experiment->audience, 1) : '0.0' }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No A/B results yet. Create a campaign with an alternative message or holdout group.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
