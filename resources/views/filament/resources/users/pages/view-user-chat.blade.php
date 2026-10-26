<x-filament-panels::page>
    <div class="kb-profile-heading">
        <a class="kb-profile-return" href="{{ \App\Filament\Resources\Users\UserResource::getUrl() }}">← Back to people</a>
        <div class="kb-profile-heading-title">
            <h2>{{ $user->first_name }}</h2>
            <span class="kb-chat-state {{ $user->support_status === 'closed' ? 'kb-state-closed' : '' }}">{{ ucfirst($user->support_status) }}</span>
        </div>
        <p>{{ $user->username ? '@'.$user->username : 'Telegram member' }} · Joined {{ $user->joined_at?->timezone(config('app.display_timezone'))->format('M d, Y') ?? 'unknown' }}</p>
    </div>
    <div class="kb-profile-layout">
        <aside class="kb-profile-information">
            <section class="kb-profile-card">
                <div class="kb-profile-section-heading"><h3>User profile</h3><span>Telegram</span></div>
                <div class="kb-profile-id">
                    <span class="kb-chat-avatar kb-profile-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->first_name ?: '?', 0, 1)) }}</span>
                    <div><strong>{{ $user->first_name }}</strong><p>{{ $user->username ? '@'.$user->username : 'Telegram member' }}</p></div>
                </div>
                <dl class="kb-profile-facts">
                    <div><dt>Chat ID</dt><dd>{{ $user->tg_chat_id }}</dd></div>
                    <div><dt>Language</dt><dd>{{ strtoupper($user->preferred_language ?: ($user->language ?: '—')) }}</dd></div>
                    <div><dt>Joined</dt><dd>{{ $user->joined_at?->timezone(config('app.display_timezone'))->format('d M Y · g:i A') ?? '—' }}</dd></div>
                    <div><dt>Last active</dt><dd>{{ $user->last_active_at?->diffForHumans() ?? 'Never' }}</dd></div>
                    <div><dt>Blocked the bot</dt><dd>{{ $user->blocked_bot ? 'Yes' : 'No' }}</dd></div>
                </dl>
            </section>
            <section class="kb-profile-card">
                <div class="kb-profile-section-heading"><h3>Preferences</h3></div>
                <div class="kb-profile-detail"><strong>Topics</strong>
                    <div class="kb-profile-chips">
                        @forelse($user->topics ?? [] as $topic)
                            <span>{{ ucfirst($topic) }}</span>
                        @empty <span>All topics</span> @endforelse
                    </div>
                </div>
                <dl class="kb-profile-facts">
                    <div><dt>Promotions</dt><dd>{{ $user->marketing_subscribed ? 'Subscribed' : 'Unsubscribed' }}</dd></div>
                    <div><dt>Daily limit</dt><dd>{{ $user->daily_message_limit }}</dd></div>
                </dl>
            </section>
            <section class="kb-profile-card">
                <div class="kb-profile-section-heading"><h3>Support details</h3></div>
                <dl class="kb-profile-facts">
                    <div><dt>Status</dt><dd>{{ ucfirst($user->support_status) }}</dd></div>
                    <div><dt>Assigned to</dt><dd>{{ $assignedAdmin?->name ?? 'Unassigned' }}</dd></div>
                    <div><dt>Acquisition</dt><dd>{{ $user->source ?: 'Organic' }}</dd></div>
                </dl>
            </section>
            <section class="kb-profile-card">
                <div class="kb-profile-section-heading"><h3>Internal notes</h3><span>Staff only</span></div>
                @if(filled($user->support_note))
                    <div class="kb-profile-note">{{ $user->support_note }}</div>
                @else
                    <p class="kb-profile-empty">No internal notes added.</p>
                @endif
                <p class="kb-profile-edit-hint">Use “Preferences &amp; support” above to edit notes and preferences.</p>
            </section>
        </aside>
        <section class="kb-profile-chat" aria-label="Conversation with {{ $user->first_name }}">
            @include('filament.partials.conversation-thread', ['user' => $user, 'messages' => $messages])
        </section>
    </div>
</x-filament-panels::page>
