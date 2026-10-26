<x-filament-panels::page>
<div class="studio-dashboard studio-inbox-page">
    <header class="studio-page-intro">
        <div><h2>Good {{ now()->timezone(config('app.display_timezone'))->hour < 12 ? 'morning' : (now()->timezone(config('app.display_timezone'))->hour < 18 ? 'afternoon' : 'evening') }}, {{ explode(' ',auth()->user()->name)[0] }} 👋</h2>
            <p>Here's what's happening with your audience today.</p></div>
        <div class="studio-period-selector" role="group" aria-label="Reporting time period">
            @foreach([7,30,90] as $dayCount)
                <button type="button" wire:click="setPeriod({{ $dayCount }})" wire:loading.attr="disabled" class="{{ $period === $dayCount ? 'is-active' : '' }}">{{ $dayCount }}D</button>
            @endforeach
        </div>
    </header>
    @include('filament.partials.studio-kpis')
    <div class="studio-dash-two">
        <section class="studio-dashboard-card studio-dash-overview">
            <header><div><h3>Performance Overview</h3><p>Audience growth and campaign messages sent</p></div><div class="studio-chart-legend"><span class="studio-legend-green"></span>Messages sent<span class="studio-legend-blue"></span>New people</div></header>
            <div class="studio-chart-container" role="img" aria-label="{{ $period }}-day chart comparing real campaign sends to audience growth">
                <div class="studio-chart-vertical-labels"><span>{{ number_format(max(max(array_column($deliveries, 'count') ?: [0]), max($joins ?: [0]))) }}</span><span>0</span></div>
                <svg viewBox="0 0 720 185" preserveAspectRatio="none" aria-hidden="true">
                    <line x1="0" y1="18" x2="720" y2="18" stroke="#e7eff0" stroke-dasharray="3 6"/>
                    <line x1="0" y1="91" x2="720" y2="91" stroke="#e7eff0" stroke-dasharray="3 6"/>
                    <line x1="0" y1="170" x2="720" y2="170" stroke="#e7eff0"/>
                    <polyline points="{{ $sendingLine }}" stroke="#159963" stroke-width="2.7" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    <polyline points="{{ $joiningLine }}" stroke="#3986e9" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                </svg>
                <div class="studio-chart-axis"><span>{{ \Carbon\Carbon::parse(array_key_first($joins))->format('M d') }}</span><span>{{ now()->timezone(config('app.display_timezone'))->format('M d') }}</span></div>
            </div>
        </section>
        <section class="studio-dashboard-card studio-dash-actions">
            <header><h3>Quick Actions</h3></header>
            <a href="{{ \App\Filament\Resources\Broadcasts\BroadcastResource::getUrl('create') }}"><span class="studio-action-icon green">➤</span>New Campaign <span>→</span></a>
            <a href="{{ \App\Filament\Resources\Automations\AutomationResource::getUrl('create') }}"><span class="studio-action-icon blue">⟳</span>Create Automation <span>→</span></a>
            <a href="{{ \App\Filament\Resources\Segments\SegmentResource::getUrl() }}"><span class="studio-action-icon turquoise">♧</span>Add New Audience <span>→</span></a>
            <a href="{{ \App\Filament\Pages\Reports::getUrl() }}"><span class="studio-action-icon coral">▥</span>View Reports <span>→</span></a>
        </section>
    </div>
    <div class="studio-dash-two studio-dash-footer">
        <section class="studio-dashboard-card">
            <header><div><h3>Active conversations</h3><p>Recent people waiting for support</p></div><a class="studio-card-link" href="{{ \App\Filament\Pages\Inbox::getUrl() }}">Open Inbox →</a></header>
            @forelse($conversations as $person)
                <a class="studio-dashboard-list-row" href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view',['record'=>$person]) }}">
                    <span class="studio-avatar studio-avatar-sm">{{ mb_strtoupper(mb_substr($person->first_name ?: '?',0,1)) }}</span>
                    <div><strong>{{ $person->first_name }}</strong><small>{{ $person->username ? '@'.$person->username : 'Telegram member' }} · {{ $person->last_active_at?->diffForHumans() ?? 'No activity yet' }}</small></div>
                    <span class="studio-tag green">{{ ucfirst($person->support_status) }}</span>
                </a>
            @empty
                <div class="studio-dashboard-empty"><strong>All caught up</strong><p>New conversations will appear here.</p></div>
            @endforelse
        </section>
        <section class="studio-dashboard-card">
            <header><div><h3>Campaign Performance</h3><p>Recent campaigns and recorded results</p></div><a class="studio-card-link" href="{{ \App\Filament\Resources\Broadcasts\BroadcastResource::getUrl() }}">View all →</a></header>
            @forelse($campaigns as $campaign)
                <div class="studio-dashboard-list-row">
                    <span class="studio-action-icon green">➤</span>
                    <div><strong>{{ $campaign->name ?: 'Campaign #'.$campaign->id }}</strong><small>{{ number_format($campaign->sent) }} messages sent</small></div>
                    <span class="studio-tag {{ in_array($campaign->status->value,['completed']) ? 'green' : 'neutral' }}">{{ ucfirst($campaign->status->value) }}</span>
                </div>
            @empty
                <div class="studio-dashboard-empty"><strong>No campaigns yet</strong><p>Create a campaign to start tracking delivery performance.</p></div>
            @endforelse
        </section>
    </div>
    <div class="studio-dashboard-footnote">
        <span>{{ number_format($activeJourneys) }} active automation journeys</span>
        <span>{{ number_format($pendingUpdates) }} incoming updates awaiting processing</span>
    </div>
</div>
</x-filament-panels::page>
