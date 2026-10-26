<x-filament-panels::page>
<div class="studio-reports">
    <header class="studio-section-heading">
        <div><h2>Reports / Analytics</h2><p>Understand your impact. Turn real campaign and audience activity into better decisions.</p></div>
        <div class="studio-period-selector" role="group" aria-label="Report period">
            @foreach([7,30,90] as $days)
                <button type="button" wire:click="setPeriod({{ $days }})" wire:loading.attr="disabled" class="{{ $period === $days ? 'is-active' : '' }}">{{ $days }}D</button>
            @endforeach
        </div>
    </header>
    <nav class="studio-report-tabs" aria-label="Report sections">
        <a class="is-active" href="#studio-report-overview">Overview</a>
        <a href="#studio-report-engagement">Engagement</a>
        <a href="#studio-report-campaigns">Campaigns</a>
        <a href="#studio-report-experiments">Experiments</a>
    </nav>
    <section class="studio-kpis studio-report-stats" id="studio-report-overview" aria-label="Reporting overview">
        @foreach([
            ['Total Users', $totalUsers, 'All recorded Telegram members', '♧'],
            ['Active Users', $activeUsers, 'Active in selected period', '◉'],
            ['Messages Sent', $sentTotal, 'Across campaigns created in period', '➤'],
            ['Campaign CTR', $ctr === null ? '—' : $ctr.'%', 'Summed unique clickers divided by sent messages', '▥']
        ] as [$label,$value,$hint,$icon])
            <article class="studio-kpi"><span class="studio-kpi-icon" aria-hidden="true">{{ $icon }}</span><div class="studio-kpi-copy"><span class="studio-kpi-label">{{ $label }}</span><strong class="studio-kpi-value" title="{{ $hint }}">{{ is_numeric($value) ? number_format($value) : $value }}</strong></div></article>
        @endforeach
    </section>
    <div class="studio-report-visuals" id="studio-report-engagement">
        <section class="studio-dashboard-card">
            <header><div><h3>User Growth</h3><p>New members and campaign deliveries · {{ $period }} days</p></div><div class="studio-chart-legend"><span class="studio-legend-blue"></span>New users<span class="studio-legend-green"></span>Sent</div></header>
            <div class="studio-chart-container" role="img" aria-label="Real audience growth and message delivery chart">
                <div class="studio-chart-vertical-labels"><span>{{ number_format($chartMax) }}</span><span>0</span></div>
                <svg viewBox="0 0 720 185" preserveAspectRatio="none" aria-hidden="true">
                    <line x1="0" x2="720" y1="18" y2="18" stroke="#e7eff0" stroke-dasharray="3 5"/>
                    <line x1="0" x2="720" y1="91" y2="91" stroke="#e7eff0" stroke-dasharray="3 5"/>
                    <line x1="0" x2="720" y1="170" y2="170" stroke="#e7eff0"/>
                    <polyline points="{{ $joiningLine }}" fill="none" stroke="#3988e5" stroke-width="2.5" stroke-linejoin="round"/>
                    <polyline points="{{ $sendingLine }}" fill="none" stroke="#159963" stroke-width="2.5" stroke-linejoin="round"/>
                </svg>
                <div class="studio-chart-axis"><span>{{ \Carbon\Carbon::parse(array_key_first($joins))->format('M d') }}</span><span>{{ now()->timezone(config('app.display_timezone'))->format('M d') }}</span></div>
            </div>
        </section>
        <section class="studio-dashboard-card">
            <header><div><h3>Campaign Performance</h3><p>Sent message volume by campaign</p></div></header>
            <div class="studio-report-bars">
                @php $highest = max(1, (int)$campaigns->max('sent')); @endphp
                @forelse($campaigns->take(5) as $campaign)
                    <div class="studio-report-bar-row">
                        <span title="{{ $campaign->name ?: 'Campaign #'.$campaign->id }}">{{ \Illuminate\Support\Str::limit($campaign->name ?: 'Campaign #'.$campaign->id, 19) }}</span>
                        <div><span style="width:{{ round(100 * $campaign->sent/$highest) }}%"></span></div>
                        <strong>{{ number_format($campaign->sent) }}</strong>
                    </div>
                @empty
                    <div class="studio-dashboard-empty"><strong>No campaigns in this period</strong><p>Send a campaign to see its delivery volume.</p></div>
                @endforelse
            </div>
        </section>
    </div>
    <section class="studio-dashboard-card" id="studio-report-campaigns">
        <header><div><h3>Top Campaigns</h3><p>Reach, engagement and conversions from your recorded delivery results</p></div></header>
        <div class="studio-report-table-wrap"><table class="studio-report-table">
            <thead><tr><th>Campaign</th><th>Status</th><th>Sent</th><th>Unique clickers</th><th>Total taps</th><th>Conversions</th></tr></thead>
            <tbody>
                @forelse($campaigns as $campaign)
                    <tr>
                        <td>{{ $campaign->name ?: 'Campaign #'.$campaign->id }}</td>
                        <td><span class="studio-tag {{ $campaign->status->value === 'completed' ? 'green' : 'neutral' }}">{{ ucfirst($campaign->status->value) }}</span></td>
                        <td>{{ number_format($campaign->sent) }}</td>
                        <td>{{ number_format($clicks[$campaign->id]->people ?? 0) }}</td>
                        <td>{{ number_format($clicks[$campaign->id]->taps ?? 0) }}</td>
                        <td>{{ number_format($conversions[$campaign->id]->total ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No campaign results recorded in this period.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
    <section class="studio-dashboard-card" id="studio-report-experiments">
        <header><div><h3>Experiment results</h3><p>A/B variants and holdouts are counted only when real experiment assignments exist.</p></div></header>
        <div class="studio-report-table-wrap"><table class="studio-report-table">
            <thead><tr><th>Campaign</th><th>Group</th><th>Audience</th><th>Sent</th><th>Clickers</th><th>Converted</th><th>Conversion rate</th></tr></thead>
            <tbody>
                @forelse($experiments as $experiment)
                    <tr><td>#{{ $experiment->broadcast_id }}</td><td>{{ strtoupper($experiment->cohort) }}</td><td>{{ $experiment->audience }}</td><td>{{ $experiment->sent }}</td><td>{{ $experiment->clickers }}</td><td>{{ $experiment->converted }}</td><td>{{ $experiment->audience ? number_format(100 * $experiment->converted/$experiment->audience,1) : '0.0' }}%</td></tr>
                @empty
                    <tr><td colspan="7">No experiment data yet. Add an alternative message or holdout when composing a campaign.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <p class="studio-report-note">Unique clickers are summed per campaign; the same person can appear in multiple campaigns. Conversions require verified integration events.</p>
    </section>
</div>
</x-filament-panels::page>
