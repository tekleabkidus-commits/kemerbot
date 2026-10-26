<x-filament-panels::page>
    <div class="ks-page">
        <header class="ks-page-head">
            <div><span class="ks-eyebrow">CAMPAIGN PLANNING</span><h2 class="ks-heading">Your content calendar.</h2><p class="ks-subtitle">See what's scheduled, review upcoming campaigns and keep delivery dates clear. All times are shown in Addis Ababa time.</p></div>
            <div class="ks-page-actions">
                <a class="ks-action primary" href="{{ \App\Filament\Resources\Broadcasts\BroadcastResource::getUrl('create') }}">＋ Create campaign</a>
            </div>
        </header>
        <section class="ks-panel ks-panel-pad">
            <div class="ks-calendar-toolbar">
                <div><span class="ks-eyebrow" style="display:block;margin-bottom:5px">SCHEDULED CAMPAIGNS</span><h3 class="ks-calendar-month">{{ $monthLabel }}</h3></div>
                <div class="ks-calendar-nav" role="group" aria-label="Browse calendar months">
                    <button type="button" wire:click="previousMonth" wire:loading.attr="disabled" wire:target="previousMonth" aria-label="Previous month">←</button>
                    <button type="button" wire:click="currentMonth" wire:loading.attr="disabled" wire:target="currentMonth">Today</button>
                    <button type="button" wire:click="nextMonth" wire:loading.attr="disabled" wire:target="nextMonth" aria-label="Next month">→</button>
                </div>
            </div>
            <p style="font-size:11px;color:#829185;margin:12px 0 15px">Timezone: {{ $timezone }} · {{ $monthEvents->count() }} scheduled campaign{{ $monthEvents->count() === 1 ? '' : 's' }} visible</p>
            <div class="ks-calendar-wrap">
                <div class="ks-calendar" role="grid" aria-label="Campaign schedule for {{ $monthLabel }}">
                    @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
                        <div class="ks-calendar-dayname" role="columnheader">{{ $day }}</div>
                    @endforeach
                    @foreach($weeks as $week)
                        @foreach($week as $day)
                            <div class="ks-calendar-cell {{ $day['isCurrentMonth'] ? '' : 'is-other' }} {{ $day['isToday'] ? 'is-today' : '' }}" role="gridcell" aria-label="{{ $day['date'] }}: {{ $day['events']->count() }} campaigns">
                                <time datetime="{{ $day['date'] }}">{{ $day['label'] }}</time>
                                @foreach($day['events']->take(3) as $campaign)
                                    <span class="ks-calendar-event" title="{{ $campaign->name ?: 'Campaign #'.$campaign->id }} — {{ $campaign->scheduled_at->timezone($timezone)->format('H:i') }}">
                                        <small>{{ $campaign->scheduled_at->timezone($timezone)->format('H:i') }}</small> {{ $campaign->name ?: 'Campaign #'.$campaign->id }}
                                    </span>
                                @endforeach
                                @if($day['events']->count() > 3)
                                    <small style="font-size:9px;color:#677c70">+{{ $day['events']->count() - 3 }} more</small>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </section>
        <section class="ks-panel ks-panel-pad">
            <div class="ks-panel-header"><div><h3>Scheduled this month</h3><p>Details for the events shown above</p></div><span class="ks-chip">{{ $monthEvents->count() }} upcoming</span></div>
            <div class="ks-table-scroll"><table class="ks-table">
                <thead><tr><th>Campaign</th><th>Send time (Addis)</th><th>Topic</th><th>Recurrence</th><th>Approval</th></tr></thead>
                <tbody>
                    @forelse($monthEvents as $campaign)
                        <tr>
                            <td><strong>{{ $campaign->name ?: 'Campaign #'.$campaign->id }}</strong></td>
                            <td>{{ $campaign->scheduled_at->timezone($timezone)->format('D, d M · H:i') }}</td>
                            <td><span class="ks-chip">{{ ucfirst($campaign->topic ?: 'General') }}</span></td>
                            <td>{{ $campaign->recurrence ? 'Recurring' : 'One-time' }}</td>
                            <td><span class="ks-chip {{ $campaign->approved_at ? '' : 'amber' }}">{{ $campaign->approved_at ? 'Approved' : 'Needs review' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No scheduled campaigns for this month.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </section>
    </div>
</x-filament-panels::page>
