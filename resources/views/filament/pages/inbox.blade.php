<x-filament-panels::page>
    @once
        <link rel="stylesheet" href="{{ asset('css/kemer-chat.css') }}">
    @endonce
    <div class="kb-inbox-layout {{ $selectedUser ? 'kb-chat-has-selection' : '' }}">
        <aside class="kb-chat-list-panel" aria-label="Conversations">
            <div class="kb-chat-list-heading">
                <div>
                    <h2>Inbox</h2>
                    <p>{{ $people->count() }} conversations shown</p>
                </div>
                <span class="kb-chat-heading-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h11"/></svg>
                </span>
            </div>
            <div class="kb-chat-controls">
                <label class="sr-only" for="kb-inbox-search">Search conversations</label>
                <input id="kb-inbox-search" type="search" wire:model.live.debounce.350ms="search" placeholder="Search conversations…">
                <label class="sr-only" for="kb-inbox-filter">Conversation status</label>
                <select id="kb-inbox-filter" wire:model.live="statusFilter">
                    <option value="active">Active</option>
                    <option value="closed">Resolved</option>
                    <option value="all">All</option>
                </select>
            </div>
            <div class="kb-chat-conversation-list">
                @forelse($people as $person)
                    @php $latest = $person->telegramMessages->first(); @endphp
                    <div class="kb-chat-contact {{ $selectedUser?->id === $person->id ? 'is-selected' : '' }}" wire:key="inbox-person-{{ $person->id }}">
                        <button type="button" wire:click="selectConversation({{ $person->id }})" class="kb-chat-contact-avatar" aria-label="Open conversation with {{ $person->first_name }}">
                            {{ mb_strtoupper(mb_substr($person->first_name ?: '?', 0, 1)) }}
                        </button>
                        <div class="kb-chat-contact-content">
                            <div class="kb-chat-contact-top">
                                <a class="kb-chat-contact-name" href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $person]) }}" title="Open profile">{{ $person->first_name }}</a>
                                <time>{{ $latest?->sent_at?->diffForHumans(short: true) ?? $person->last_active_at?->diffForHumans(short: true) ?? '—' }}</time>
                            </div>
                            <button type="button" class="kb-chat-contact-preview" wire:click="selectConversation({{ $person->id }})" aria-label="Read messages from {{ $person->first_name }}">
                                <span class="kb-chat-handle">{{ $person->username ? '@'.$person->username : 'Telegram member' }}</span>
                                <span class="kb-chat-excerpt">{{ $latest ? \Illuminate\Support\Str::limit($latest->text ?: '['.$latest->type.']', 65) : 'Open the conversation' }}</span>
                            </button>
                        </div>
                        @if($person->support_status === 'open')
                            <span class="kb-chat-active-indicator" title="Needs reply"></span>
                        @endif
                    </div>
                @empty
                    <div class="kb-chat-list-empty">No conversations match these filters.</div>
                @endforelse
                @if($people->count() >= $conversationLimit && $conversationLimit < 500)
                    <button class="kb-chat-older" type="button" wire:click="showMoreConversations">Show more conversations</button>
                @endif
            </div>
        </aside>
        <section class="kb-chat-thread-panel" aria-label="Selected chat">
            @if($selectedUser)
                <button type="button" class="kb-chat-mobile-back" wire:click="backToList">← All conversations</button>
            @endif
            @include('filament.partials.conversation-thread', ['user' => $selectedUser, 'messages' => $messages])
        </section>
    </div>
</x-filament-panels::page>
