@if($user)
<aside class="studio-profile-rail" aria-label="Telegram member profile">
    <div class="studio-profile-identity">
        <div class="studio-profile-main">
            <span class="studio-avatar studio-avatar-xl" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->first_name ?: '?',0,1)) }}</span>
            <div>
                <a class="studio-profile-name" href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $user]) }}">{{ $user->first_name }}</a>
                <div class="studio-profile-handle">{{ $user->username ? '@'.$user->username : 'Telegram member' }}</div>
                <div class="studio-profile-tags"><span class="studio-tag green">Telegram member</span><span class="studio-tag green">{{ $user->blocked_bot ? 'Blocked bot' : 'Active' }}</span></div>
            </div>
        </div>
    </div>
    @if($editableTabs ?? false)
        <div class="studio-profile-tabs" role="tablist" aria-label="Profile sections">
            @foreach(['profile' => 'Profile', 'activity' => 'Activity', 'campaigns' => 'Campaigns'] as $tab=>$label)
                <button type="button" class="{{ $profileTab === $tab ? 'is-active' : '' }}" wire:click="changeProfileTab('{{ $tab }}')" role="tab" aria-selected="{{ $profileTab === $tab ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>
    @endif
    @if(!($editableTabs ?? false) || $profileTab === 'profile')
        <div class="studio-profile-scroll">
            <section class="studio-profile-group">
                <header><h3><span aria-hidden="true">♙</span>User details</h3><a class="studio-small-action" href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $user]) }}">Edit</a></header>
                <dl class="studio-profile-facts">
                    <div><dt>User ID</dt><dd title="Telegram chat ID">{{ $user->tg_chat_id }}</dd></div>
                    <div><dt>Language</dt><dd>{{ strtoupper($user->preferred_language ?: ($user->language ?: '—')) }}</dd></div>
                    <div><dt>Timezone</dt><dd>UTC+3 (Addis Ababa)</dd></div>
                    <div><dt>Added</dt><dd>{{ $user->joined_at?->timezone(config('app.display_timezone'))->format('M d, Y, g:i A') ?? '—' }}</dd></div>
                    <div><dt>Last active</dt><dd>{{ $user->last_active_at?->diffForHumans() ?? '—' }}</dd></div>
                    <div><dt>Daily limit</dt><dd>{{ $user->daily_message_limit }} messages</dd></div>
                </dl>
            </section>
            <section class="studio-profile-group">
                <header><h3><span aria-hidden="true">◇</span> Tags</h3><a class="studio-small-action" href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $user]) }}">Manage</a></header>
                <div class="studio-topic-chips">
                    @forelse($user->topics ?? [] as $i=>$topic)
                        <span class="studio-tag {{ $i % 3 === 0 ? 'green' : ($i % 3 === 1 ? 'blue' : 'purple') }}">{{ ucfirst($topic) }}</span>
                    @empty
                        <span class="studio-tag neutral">No topics selected</span>
                    @endforelse
                </div>
            </section>
            <section class="studio-profile-group">
                <header><h3><span aria-hidden="true">➤</span> Notifications &amp; consent</h3></header>
                <div class="studio-consent-row"><span>Promotional messages</span><span class="studio-switch {{ $user->marketing_subscribed && ! $user->blocked_bot ? 'on' : '' }}" role="img" aria-label="{{ $user->marketing_subscribed && ! $user->blocked_bot ? 'Subscribed' : 'Not subscribed' }}"></span></div>
                <div class="studio-consent-row"><span>Bot contact</span><span class="studio-tag {{ $user->blocked_bot ? 'red' : 'green' }}">{{ $user->blocked_bot ? 'Blocked' : 'Allowed' }}</span></div>
                <div class="studio-consent-row"><span>Consent status</span><span class="studio-tag green">{{ $user->marketing_subscribed ? 'Subscribed' : 'Unsubscribed' }}</span></div>
                <p class="studio-profile-hint">Promotional campaigns respect each person's saved subscription preferences and limits.</p>
            </section>
            <section class="studio-profile-group">
                <header><h3><span aria-hidden="true">▤</span> Notes</h3>
                    @if(($editableTabs ?? false) && !auth()->user()->isViewer())
                        <button class="studio-small-action" type="button" wire:click="startNote">✎ {{ $user->support_note ? 'Edit note' : 'Add note' }}</button>
                    @else
                        <a class="studio-small-action" href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $user]) }}">Profile</a>
                    @endif
                </header>
                @if(($editableTabs ?? false) && $editingNote)
                    <div class="studio-note-editor">
                        <label for="studio-note">Staff-only note</label>
                        <textarea id="studio-note" wire:model="noteDraft" maxlength="4000" rows="4" placeholder="Write a private support note…"></textarea>
                        @error('noteDraft') <p role="alert">{{ $message }}</p> @enderror
                        <div><button type="button" class="studio-action-outline" wire:click="cancelNote">Cancel</button><button type="button" class="studio-action-primary" wire:click="saveNote">Save note</button></div>
                    </div>
                @elseif($user->support_note)
                    <div class="studio-note-card"><span aria-hidden="true">▤</span><p>{{ $user->support_note }}</p></div>
                @else
                    <p class="studio-empty-note">No internal notes yet.</p>
                @endif
            </section>
            <section class="studio-profile-group studio-profile-last">
                <header><h3><span aria-hidden="true">▣</span> Quick actions</h3></header>
                <div class="studio-quick-actions">
                    <a href="{{ \App\Filament\Resources\Broadcasts\BroadcastResource::getUrl('create') }}">↗ New campaign</a>
                    <a href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $user]) }}">♧ View profile</a>
                </div>
            </section>
        </div>
    @elseif($profileTab === 'activity')
        <div class="studio-profile-scroll studio-timeline">
            <h3>Recent activity</h3>
            @forelse($recentActivity as $activity)
                <article>
                    <span class="studio-timeline-dot"></span>
                    <div><strong>{{ $activity->direction === \App\Enums\MessageDirection::Inbound ? 'Incoming message' : 'Admin reply' }}</strong><p>{{ \Illuminate\Support\Str::limit($activity->text ?: '['.$activity->type.']', 110) }}</p><small>{{ $activity->sent_at?->timezone(config('app.display_timezone'))->format('d M, g:i A') }}</small></div>
                </article>
            @empty
                <p class="studio-empty-note">No recent conversation activity.</p>
            @endforelse
        </div>
    @else
        <div class="studio-profile-scroll studio-timeline">
            <h3>Campaign history</h3>
            @forelse($recentCampaigns as $campaign)
                <article>
                    <span class="studio-timeline-dot"></span><div><strong>{{ $campaign->name ?: 'Campaign #'.$campaign->broadcast_id }}</strong><p>Delivery status: {{ ucfirst($campaign->status) }}</p><small>{{ $campaign->sent_at ? \Carbon\Carbon::parse($campaign->sent_at)->timezone(config('app.display_timezone'))->format('d M, g:i A') : 'Not sent' }}</small></div>
                </article>
            @empty
                <p class="studio-empty-note">No campaign deliveries recorded for this person.</p>
            @endforelse
        </div>
    @endif
</aside>
@else
<aside class="studio-profile-rail studio-no-selection"><p>Choose a person to see their profile.</p></aside>
@endif
