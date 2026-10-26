<x-filament-panels::page>
    <div class="ks-page">
        <header class="ks-page-head">
            <div>
                <span class="ks-eyebrow">AUDIENCE OVERVIEW · {{ now()->timezone(config('app.display_timezone'))->format('D, d M Y') }}</span>
                <h2 class="ks-heading">Good {{ now()->timezone(config('app.display_timezone'))->hour < 12 ? 'morning' : (now()->timezone(config('app.display_timezone'))->hour < 18 ? 'afternoon' : 'evening') }}, {{ explode(' ', auth()->user()->name)[0] }}.</h2>
                <p class="ks-subtitle">Your KemerBet audience, campaigns and conversations in one place. Figures below use your live records.</p>
            </div>
            <nav class="ks-page-actions" aria-label="Dashboard quick actions">
                <a class="ks-action" href="{{ \App\Filament\Pages\Reports::getUrl() }}">View reports</a>
                <a class="ks-action primary" href="{{ \App\Filament\Resources\Broadcasts\BroadcastResource::getUrl('create') }}">＋ New campaign</a>
            </nav>
        </header>
        <section class="ks-stats-grid" aria-label="Audience statistics">
            @foreach([
                ['Reachable audience', $subscribers, 'Subscribed and not blocked', '◎'],
                ['Joined today', $kpis['new_today'], 'New audience members', '↗'],
                ['Active this week', $kpis['active_7d'], 'Interacted in the last 7 days', '◉'],
                ['Needs a reply', $openInbox, 'Open support conversations', '▤']
            ] as [$label, $value, $description, $symbol])
                <article class="ks-stat">
                    <div class="ks-stat-head"><span class="ks-stat-icon" aria-hidden="true">{{ $symbol }}</span><span>{{ $label }}</span></div>
                    <strong class="ks-stat-number">{{ number_format($value) }}</strong>
                    <span class="ks-stat-detail">{{ $description }}</span>
                </article>
            @endforeach
        </section>
        <div class="ks-grid">
            <section class="ks-panel ks-panel-pad ks-span-8">
                <div class="ks-panel-header">
                    <div><h3>Audience growth</h3><p>New members over the past 14 days</p></div>
                    <span class="ks-chip">{{ number_format(array_sum($joins)) }} joins</span>
                </div>
                @if(count($joins))
                    @php $maxJoin = max(1, max($joins)); @endphp
                    <div class="ks-chart-bars" role="img" aria-label="Daily new members, last 14 days">
                        @foreach($joins as $date => $count)
                            <div class="ks-chart-bar">
                                <span title="{{ \Carbon\Carbon::parse($date)->format('d M') }}: {{ $count }} joins" style="height:{{ max(3, (int) round($count / $maxJoin * 175)) }}px"></span>
                                <small>{{ \Carbon\Carbon::parse($date)->format('d') }}</small>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="ks-empty"><span class="symbol">◌</span><h4>No audience activity yet</h4><p>New joins will appear as Telegram members start your bot.</p></div>
                @endif
                <div class="ks-page-actions" style="margin-top:18px">
                    <a class="ks-action" href="{{ \App\Filament\Resources\Users\UserResource::getUrl() }}">Explore audience</a>
                    <a class="ks-action" href="{{ \App\Filament\Resources\Segments\SegmentResource::getUrl() }}">Saved audiences</a>
                </div>
            </section>
            <section class="ks-panel ks-panel-pad ks-span-4">
                <div class="ks-panel-header"><div><h3>Quick actions</h3><p>Keep your workspace moving</p></div></div>
                @foreach([
                    [\App\Filament\Resources\Broadcasts\BroadcastResource::getUrl('create'), '↗', 'Create a campaign', 'Prepare and send an update'],
                    [\App\Filament\Resources\Automations\AutomationResource::getUrl('create'), '⟳', 'Build an automation', 'Design a customer journey'],
                    [\App\Filament\Pages\Inbox::getUrl(), '▤', 'Open support inbox', 'Reply to your audience'],
                    [\App\Filament\Pages\DeliveryCenter::getUrl(), '✓', 'Check delivery health', 'See messages that need attention']
                ] as [$url, $symbol, $title, $description])
                    <a class="ks-list-row" style="text-decoration:none" href="{{ $url }}">
                        <span class="ks-list-avatar" aria-hidden="true">{{ $symbol }}</span>
                        <span class="ks-list-content"><strong>{{ $title }}</strong><small>{{ $description }}</small></span>
                        <span style="color:#477b60" aria-hidden="true">→</span>
                    </a>
                @endforeach
            </section>
        </div>
        <div class="ks-grid">
            <section class="ks-panel ks-panel-pad ks-span-4">
                <div class="ks-panel-header">
                    <div><h3>Active conversations</h3><p>Recent support activity</p></div>
                    <a href="{{ \App\Filament\Pages\Inbox::getUrl() }}">Open inbox →</a>
                </div>
                @forelse($conversations as $person)
                    <a class="ks-list-row" href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $person]) }}" style="text-decoration:none">
                        <span class="ks-list-avatar">{{ mb_strtoupper(mb_substr($person->first_name ?: '?', 0, 1)) }}</span>
                        <span class="ks-list-content">
                            <strong>{{ $person->first_name }}</strong>
                            <small>{{ $person->username ? '@'.$person->username : 'Telegram member' }} · {{ $person->last_active_at?->diffForHumans() ?? 'No activity yet' }}</small>
                        </span>
                        <span class="ks-chip {{ $person->support_status === 'pending' ? 'amber' : '' }}">{{ ucfirst($person->support_status) }}</span>
                    </a>
                @empty
                    <div class="ks-empty"><span class="symbol">✉</span><h4>All conversations handled</h4><p>New support requests will appear here.</p></div>
                @endforelse
            </section>
            <section class="ks-panel ks-panel-pad ks-span-4">
                <div class="ks-panel-header">
                    <div><h3>Recent campaigns</h3><p>The latest delivery activity</p></div>
                    <a href="{{ \App\Filament\Resources\Broadcasts\BroadcastResource::getUrl() }}">View all →</a>
                </div>
                @forelse($campaigns as $campaign)
                    <div class="ks-list-row">
                        <span class="ks-list-avatar purple" aria-hidden="true">↗</span>
                        <div class="ks-list-content">
                            <strong>{{ $campaign->name ?: 'Campaign #'.$campaign->id }}</strong>
                            <small>{{ number_format($campaign->sent) }} sent · {{ $campaign->created_at->timezone(config('app.display_timezone'))->format('d M · H:i') }}</small>
                        </div>
                        <span class="ks-chip {{ in_array($campaign->status->value, ['sending', 'preparing', 'scheduled']) ? 'amber' : (in_array($campaign->status->value, ['failed','cancelled']) ? 'rose' : '') }}">{{ ucfirst($campaign->status->value) }}</span>
                    </div>
                @empty
                    <div class="ks-empty"><span class="symbol">↗</span><h4>No campaigns yet</h4><p>Create your first campaign to see its results.</p></div>
                @endforelse
            </section>
            <section class="ks-panel ks-panel-pad ks-span-4">
                <div class="ks-panel-header">
                    <div><h3>Coming up</h3><p>Scheduled in Addis Ababa time</p></div>
                    <a href="{{ \App\Filament\Pages\Calendar::getUrl() }}">Calendar →</a>
                </div>
                @forelse($scheduled as $campaign)
                    <div class="ks-activity-line">
                        <span class="ks-activity-date"><strong>{{ $campaign->scheduled_at->timezone(config('app.display_timezone'))->format('d') }}</strong><small>{{ $campaign->scheduled_at->timezone(config('app.display_timezone'))->format('M') }}</small></span>
                        <span class="ks-list-content"><strong>{{ $campaign->name ?: 'Campaign #'.$campaign->id }}</strong><small>{{ $campaign->scheduled_at->timezone(config('app.display_timezone'))->format('H:i') }} · {{ $campaign->recurrence ? 'Recurring' : 'One-time' }}</small></span>
                    </div>
                @empty
                    <div class="ks-empty"><span class="symbol">☀</span><h4>Nothing scheduled</h4><p>New scheduled campaigns will appear here.</p></div>
                @endforelse
                <div class="ks-insight" style="margin-top:18px">
                    <strong>Workspace status</strong>
                    {{ number_format($activeJourneys) }} active automation journeys · {{ number_format($pendingUpdates) }} incoming updates awaiting processing.
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
