@if($user)
<div class="kb-chat-conversation">
    <header class="kb-chat-header">
        <div class="kb-chat-header-identity">
            <span class="kb-chat-avatar kb-chat-avatar-large" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->first_name ?: '?', 0, 1)) }}</span>
            <div class="kb-chat-header-meta">
                <div class="kb-chat-header-line">
                    <a class="kb-chat-person-link" href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $user]) }}">{{ $user->first_name }}</a>
                    <span class="kb-chat-state {{ $user->support_status === 'closed' ? 'kb-state-closed' : '' }}">{{ ucfirst($user->support_status) }}</span>
                </div>
                <div class="kb-chat-muted">{{ $user->username ? '@'.$user->username : 'Telegram member' }} · {{ $user->last_active_at?->diffForHumans() ?? 'No recent activity' }}</div>
            </div>
        </div>
        @if(auth()->user()->can('message', $user))
            <div class="kb-chat-header-actions">
                @if($user->support_status === 'closed')
                    <button type="button" class="kb-chat-outline-button" wire:click="reopenSelectedConversation" wire:loading.attr="disabled" wire:target="reopenSelectedConversation">Reopen</button>
                @else
                    <button type="button" class="kb-chat-outline-button" wire:click="resolveSelectedConversation" wire:loading.attr="disabled" wire:target="resolveSelectedConversation">Resolve</button>
                @endif
            </div>
        @endif
    </header>
    <div class="kb-chat-messages" wire:poll.12s x-data x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })" wire:key="conversation-messages-{{ $user->id }}" role="log" aria-label="Conversation history">
        @if($messages->count() >= $this->messageLimit && $this->messageLimit < 500)
            <button type="button" class="kb-chat-older" wire:click="loadEarlierMessages">Load earlier messages</button>
        @endif
        @php $previousDay = null; @endphp
        @forelse($messages as $message)
            @php
                $messageDate = $message->sent_at?->timezone(config('app.display_timezone'));
                $day = $messageDate?->format('Y-m-d');
                $outgoing = $message->direction === \App\Enums\MessageDirection::AdminOutbound;
            @endphp
            @if($day !== $previousDay)
                <div class="kb-chat-day"><span>{{ $messageDate?->isToday() ? 'Today' : ($messageDate?->isYesterday() ? 'Yesterday' : $messageDate?->format('M d, Y')) }}</span></div>
                @php $previousDay = $day; @endphp
            @endif
            <article class="kb-chat-bubble-row {{ $outgoing ? 'kb-outgoing' : 'kb-incoming' }}" wire:key="chat-message-{{ $message->id }}">
                @unless($outgoing)
                    <span class="kb-chat-avatar kb-chat-avatar-small" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->first_name ?: '?', 0, 1)) }}</span>
                @endunless
                <div class="kb-chat-bubble">
                    @if($message->type !== 'text')
                        <span class="kb-chat-media-label">{{ ucfirst(str_replace('_', ' ', $message->type)) }} message</span>
                    @endif
                    @if(filled($message->text))
                        <p>{{ $message->text }}</p>
                    @elseif($message->type === 'text')
                        <p class="kb-chat-muted">Empty message</p>
                    @endif
                    <small class="kb-chat-time">{{ $outgoing ? ($message->admin?->name ?? 'Admin').' · ' : '' }}{{ $messageDate?->format('g:i A') }}</small>
                </div>
                @if($outgoing)
                    <span class="kb-chat-avatar kb-chat-avatar-small kb-chat-agent-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($message->admin?->name ?? 'A', 0, 1)) }}</span>
                @endif
            </article>
        @empty
            <div class="kb-chat-no-messages">
                <span class="kb-chat-no-messages-icon" aria-hidden="true">✉</span>
                <strong>No messages yet</strong>
                <p>Messages and successful replies will appear here.</p>
            </div>
        @endforelse
    </div>
    @if(auth()->user()->can('message', $user))
        <form wire:submit="submitReply" class="kb-chat-composer" x-data x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.submitReply(); }">
            <label class="sr-only" for="kb-chat-reply-{{ $user->id }}">Write a message</label>
            <textarea id="kb-chat-reply-{{ $user->id }}" rows="2" maxlength="4000"
                wire:model="reply" placeholder="{{ $user->blocked_bot ? 'This person blocked the bot' : 'Type a message…' }}"
                {{ $user->blocked_bot ? 'disabled' : '' }}></textarea>
            <button type="submit" class="kb-chat-send" wire:loading.attr="disabled" wire:target="submitReply"
                {{ $user->blocked_bot ? 'disabled' : '' }} aria-label="Send message">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" width="20" height="20" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
            </button>
        </form>
        @error('reply') <p class="kb-chat-validation" role="alert">{{ $message }}</p> @enderror
        <p class="kb-chat-composer-help">Enter to send · Shift + Enter for a new line · Replies are queued for Telegram delivery</p>
    @else
        <div class="kb-chat-readonly">Read-only access: you can view conversations but cannot send replies.</div>
    @endif
</div>
@else
    <div class="kb-chat-empty-panel">
        <div class="kb-chat-empty-symbol" aria-hidden="true">✉</div>
        <h3>Select a conversation</h3>
        <p>Choose a message from the list to see the full chat history.</p>
    </div>
@endif
