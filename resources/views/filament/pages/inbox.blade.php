<x-filament-panels::page>
    <div class="studio-inbox-page">
        @include('filament.partials.studio-kpis')
        <div class="studio-inbox-workspace {{ $selectedUser ? 'has-conversation' : '' }} {{ $mobileProfile ? 'show-mobile-profile' : '' }}">
            <aside class="studio-conversations" aria-label="Conversations">
                <header class="studio-list-header">
                    <div><h2>Inbox</h2><p>{{ number_format($people->count()) }} conversations{{ trim($search) !== '' ? ' matching search' : '' }}</p></div>
                    <div class="studio-list-header-actions">
                        <label class="studio-field-select">
                            <span class="sr-only">Conversation status</span>
                            <select wire:model.live="statusFilter" aria-label="Conversation status">
                                <option value="all">All</option>
                                <option value="active">Open</option>
                                <option value="closed">Resolved</option>
                            </select>
                        </label>
                        <a class="studio-compose-button" href="{{ \App\Filament\Resources\Users\UserResource::getUrl() }}" title="Find a person to message" aria-label="Find a person to message">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="17" height="17" aria-hidden="true"><path d="M13 4H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h13a2 2 0 0 0 2-2v-8"/><path d="m12 14 9-9-2-2-9 9-1 3 3-1Z"/></svg>
                        </a>
                    </div>
                </header>
                <div class="studio-list-search">
                    <div class="studio-search-wrap">
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg>
                        <input type="search" aria-label="Search conversations" wire:model.live.debounce.350ms="search" placeholder="Search conversations…">
                    </div>
                    <span class="studio-filter-indicator" title="Filter by status using the selector above">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4-2v-4L3 5Z"/></svg>
                    </span>
                </div>
                <div class="studio-conversation-list" wire:poll.20s>
                    @forelse($people as $index=>$person)
                        @php
                            $last = $person->telegramMessages->first();
                            $unanswered = (int) ($awaitingReply[$person->id] ?? 0);
                        @endphp
                        <article class="studio-conversation-item {{ $selectedUser?->id === $person->id ? 'is-selected' : '' }}" wire:key="person-{{ $person->id }}">
                            <button class="studio-conversation-open" type="button" wire:click="selectConversation({{ $person->id }})" aria-label="Open conversation with {{ $person->first_name }}">
                                <span class="studio-avatar studio-avatar-md studio-avatar-variant-{{ $index % 6 }}" aria-hidden="true">{{ mb_strtoupper(mb_substr($person->first_name ?: '?',0,1)) }}</span>
                            </button>
                            <div class="studio-conversation-detail">
                                <div class="studio-conversation-first">
                                    <a class="studio-conversation-name" href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $person]) }}" title="Open {{ $person->first_name }}'s profile">{{ $person->first_name }}</a>
                                    <span class="studio-tag {{ $person->support_status === 'closed' ? 'neutral' : 'green' }}">{{ $person->support_status === 'closed' ? 'Resolved' : ucfirst($person->support_status) }}</span>
                                    <time>{{ $last?->sent_at?->diffForHumans(short:true) ?? $person->last_active_at?->diffForHumans(short:true) ?? '—' }}</time>
                                </div>
                                <button type="button" class="studio-conversation-excerpt" wire:click="selectConversation({{ $person->id }})" aria-label="Show messages from {{ $person->first_name }}">
                                    {{ $last ? \Illuminate\Support\Str::limit($last->text ?: '['.ucfirst($last->type).' message]', 68) : 'No messages yet' }}
                                </button>
                            </div>
                            @if($unanswered > 0)
                                <span class="studio-unanswered-count" title="{{ $unanswered }} inbound message{{ $unanswered === 1 ? '' : 's' }} awaiting reply">{{ $unanswered > 99 ? '99+' : $unanswered }}</span>
                            @endif
                        </article>
                    @empty
                        <div class="studio-list-empty"><span aria-hidden="true">◌</span><strong>No conversations found</strong><p>Try adjusting the status or search to see more people.</p></div>
                    @endforelse
                    @if($people->count() >= $conversationLimit && $conversationLimit < 500)
                        <button class="studio-more-conversations" type="button" wire:click="showMoreConversations">Load more conversations</button>
                    @endif
                </div>
            </aside>
            <section class="studio-thread-panel" aria-label="Conversation">
                @if($selectedUser)
                    <div class="studio-mobile-controls">
                        <button type="button" wire:click="backToList">← Inbox</button>
                        <button type="button" wire:click="showMobileProfile">View profile →</button>
                    </div>
                @endif
                @include('filament.partials.conversation-thread', ['user'=>$selectedUser,'messages'=>$messages,'editableTabs'=>true])
            </section>
            <div class="studio-profile-panel">
                @if($selectedUser)
                    <button class="studio-mobile-profile-back" type="button" wire:click="showMobileChat">← Back to conversation</button>
                @endif
                @include('filament.partials.studio-profile', ['user'=>$selectedUser,'editableTabs'=>true,'recentActivity'=>$recentActivity,'recentCampaigns'=>$recentCampaigns])
            </div>
        </div>
    </div>
</x-filament-panels::page>
