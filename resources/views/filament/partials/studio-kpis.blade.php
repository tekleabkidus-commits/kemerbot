@php
    $cards = [
        ['label' => 'Active Conversations', 'value' => number_format($studioStats['active']), 'icon' => 'chat', 'hint' => 'Open or awaiting reply'],
        ['label' => 'Reply Rate', 'value' => $studioStats['replyRate'] === null ? '—' : $studioStats['replyRate'].'%', 'icon' => 'users', 'hint' => $studioStats['replyWindow']],
        ['label' => 'Campaign Reach', 'value' => number_format($studioStats['reach']), 'icon' => 'plane', 'hint' => 'Unique recipients reached'],
        ['label' => 'Delivered Today', 'value' => number_format($studioStats['deliveredToday']), 'icon' => 'bars', 'hint' => 'Campaigns and personal replies'],
    ];
@endphp
<section class="studio-kpis" aria-label="Inbox performance overview">
    @foreach($cards as $card)
        <article class="studio-kpi">
            <span class="studio-kpi-icon" aria-hidden="true">
                @switch($card['icon'])
                    @case('chat')
                        <svg viewBox="0 0 24 24" fill="none"><path d="M4 5h13a3 3 0 0 1 3 3v8h-6l-4 4v-4H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/><path d="M7 9h8M7 12h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                        @break
                    @case('users')
                        <svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M2 21v-2c0-3 3-5 7-5s7 2 7 5v2H2ZM17 5a3 3 0 0 1 0 6m1 3c3 0 5 2 5 5v2h-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        @break
                    @case('plane')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                        @break
                    @default
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M5 20v-7m5 7V5m5 15V9m5 11v-5"/></svg>
                @endswitch
            </span>
            <div class="studio-kpi-copy">
                <span class="studio-kpi-label">{{ $card['label'] }}</span>
                <strong class="studio-kpi-value" title="{{ $card['hint'] }}">{{ $card['value'] }}</strong>
            </div>
            <svg class="studio-spark" viewBox="0 0 94 48" aria-hidden="true" preserveAspectRatio="none">
                <polyline points="{{ $sparkline }}" fill="none" stroke="#13915f" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </article>
    @endforeach
</section>
