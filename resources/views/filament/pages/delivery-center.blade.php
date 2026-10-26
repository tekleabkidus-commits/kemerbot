<x-filament-panels::page>
    <div class="ks-page">
        <header class="ks-page-head">
            <div><span class="ks-eyebrow">DELIVERY OPERATIONS</span><h2 class="ks-heading">Every message accounted for.</h2><p class="ks-subtitle">Monitor delivery activity and recover interrupted work without blindly resending to recipients.</p></div>
        </header>
        <section class="ks-stats-grid" aria-label="Delivery statuses">
            @foreach([
                ['Waiting', $counts['ready'] ?? 0, 'Queued for an eligible send', '◷'],
                ['Processing', $counts['processing'] ?? 0, 'Currently being handled', '⟳'],
                ['Sent', $counts['sent'] ?? 0, 'Confirmed in delivery records', '✓'],
                ['Failed', $counts['failed'] ?? 0, 'Require attention', '!']
            ] as [$label,$number,$hint,$icon])
                <article class="ks-stat"><div class="ks-stat-head"><span class="ks-stat-icon" aria-hidden="true">{{ $icon }}</span><span>{{ $label }}</span></div><strong class="ks-stat-number">{{ number_format($number) }}</strong><span class="ks-stat-detail">{{ $hint }}</span></article>
            @endforeach
        </section>
        <div class="ks-insight"><strong>Safe recovery by design</strong>Sending failures and timeouts are not always proof that Telegram never received a message. Recovery respects recorded delivery state, and retry drafts must be reviewed before sending.</div>
        <div class="ks-grid">
            <section class="ks-panel ks-panel-pad ks-span-8">
                <div class="ks-panel-header"><div><h3>Campaign deliveries</h3><p>Sending, preparing, paused, failed or partially failed campaigns</p></div><span class="ks-chip {{ $failedJobs ? 'rose' : '' }}">{{ number_format($failedJobs) }} failed queue jobs</span></div>
                <div class="ks-table-scroll"><table class="ks-table">
                    <thead><tr><th>Campaign</th><th>Status</th><th class="num">Sent</th><th>Needs attention</th><th>Action</th></tr></thead>
                    <tbody>
                    @forelse($campaigns as $campaign)
                        <tr>
                            <td><strong>{{ $campaign->name ?: 'Campaign #'.$campaign->id }}</strong></td>
                            <td><span class="ks-chip {{ $campaign->status->value === 'failed' ? 'rose' : 'amber' }}">{{ ucfirst($campaign->status->value) }}</span></td>
                            <td class="num">{{ number_format($campaign->sent) }}</td>
                            <td>{{ $campaign->failure_reason ?: ($campaign->failed ? $campaign->failed.' failed recipients' : 'Awaiting completion') }}</td>
                            <td>
                                @if(auth()->user()->isOwner() && $campaign->failed)
                                    <button type="button" class="ks-action" wire:click="retryFailed({{ $campaign->id }})" wire:loading.attr="disabled" wire:confirm="Create a reviewable draft for failed recipients only?">Create retry draft</button>
                                @else <span style="font-size:11px;color:#8a988c">—</span> @endif
                            </td>
                        </tr>
                    @empty <tr><td colspan="5">No campaigns need attention.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </section>
            <section class="ks-panel ks-panel-pad ks-span-4">
                <div class="ks-panel-header"><div><h3>Background processing</h3><p>Latest persisted operational counts</p></div></div>
                @foreach([
                    ['Incoming updates pending', $inboxPending],
                    ['Incoming updates failed', $inboxFailed],
                    ['Failed queue jobs', $failedJobs],
                    ['Personal messages needing attention', $directMessages->count()]
                ] as [$title, $number])
                    <div class="ks-list-row">
                        <span class="ks-list-avatar {{ $number ? 'purple' : '' }}" aria-hidden="true">{{ $number ? '!' : '✓' }}</span>
                        <span class="ks-list-content"><strong>{{ $title }}</strong><small>{{ $number ? 'Review the details and logs' : 'None currently recorded' }}</small></span>
                        <strong style="font-size:16px">{{ number_format($number) }}</strong>
                    </div>
                @endforeach
            </section>
        </div>
        <section class="ks-panel ks-panel-pad">
            <div class="ks-panel-header"><div><h3>Personal message outbox</h3><p>Pending, sending and failed support replies</p></div><a href="{{ \App\Filament\Pages\Inbox::getUrl() }}">Open inbox →</a></div>
            <div class="ks-table-scroll"><table class="ks-table">
                <thead><tr><th>Recipient</th><th>Status</th><th class="num">Attempts</th><th>Details</th></tr></thead>
                <tbody>
                @forelse($directMessages as $message)
                    <tr><td><a href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view',['record'=>$message->user_id]) }}" style="color:#236e50;font-weight:650">View conversation</a></td>
                        <td><span class="ks-chip {{ $message->status === 'failed' ? 'rose' : 'amber' }}">{{ ucfirst($message->status) }}</span></td>
                        <td class="num">{{ number_format($message->attempts) }}</td><td>{{ $message->last_error ?: 'Awaiting confirmed delivery' }}</td>
                    </tr>
                @empty <tr><td colspan="4">No personal messages currently need attention.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    </div>
</x-filament-panels::page>
