@if($user)
    <div class="kb-chat-conversation studio-chat">
        <header class="kb-chat-header studio-chat-header">
            <div class="kb-chat-header-identity">
                <span class="studio-avatar studio-avatar-lg" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->first_name ?: '?',0,1)) }}</span>
                <div class="kb-chat-header-meta">
                    <div class="kb-chat-header-line">
                        <a class="kb-chat-person-link" href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view',['record'=>$user]) }}">{{ $user->first_name }}</a>
                        <span class="studio-tag green">{{ $user->support_status === 'closed' ? 'Resolved' : ucfirst($user->support_status) }}</span>
                        <span class="studio-telegram-chip"><span aria-hidden="true">➤</span>Telegram member</span>
                    </div>
                    <div class="studio-chat-handle">{{ $user->username ? '@'.$user->username : 'Telegram member' }}</div>
                    <div class="studio-chat-location">Local time {{ now()->timezone(config('app.display_timezone'))->format('g:i A') }} · Addis Ababa, ETH</div>
                </div>
            </div>
            @if(auth()->user()->can('message', $user))
                <div class="studio-chat-header-actions">
                    @if($user->support_status === 'closed')
                        <button type="button" class="studio-action-outline" wire:click="reopenSelectedConversation" wire:loading.attr="disabled" wire:target="reopenSelectedConversation">Reopen</button>
                    @else
                        <button type="button" class="studio-action-outline" wire:click="resolveSelectedConversation" wire:loading.attr="disabled" wire:target="resolveSelectedConversation">Resolve</button>
                    @endif
                </div>
            @endif
        </header>
        <div class="kb-chat-messages studio-chat-messages" role="log" aria-label="Conversation history" wire:poll.12s x-data
            x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
            wire:key="conversation-messages-{{ $user->id }}">
            @if($messages->count() >= $this->messageLimit && $this->messageLimit < 500)
                <button class="studio-older" wire:click="loadEarlierMessages" type="button">Load earlier messages</button>
            @endif
            @php $previousDay = null; @endphp
            @forelse($messages as $message)
                @php
                    $sentAt = $message->sent_at?->timezone(config('app.display_timezone'));
                    $day = $sentAt?->format('Y-m-d');
                    $outgoing = $message->direction === \App\Enums\MessageDirection::AdminOutbound;
                @endphp
                @if($day !== $previousDay)
                    <div class="kb-chat-day studio-day-marker"><span>{{ $sentAt?->isToday() ? 'Today' : ($sentAt?->isYesterday() ? 'Yesterday' : $sentAt?->format('M d, Y')) }}</span></div>
                    @php $previousDay = $day; @endphp
                @endif
                <article class="kb-chat-bubble-row {{ $outgoing ? 'kb-outgoing' : 'kb-incoming' }}" wire:key="message-{{ $message->id }}">
                    @unless($outgoing)
                        <span class="studio-avatar studio-avatar-sm" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->first_name ?: '?', 0, 1)) }}</span>
                    @endunless
                    <div class="studio-message-wrapper">
                        <div class="kb-chat-bubble studio-message-bubble">
                            @if($message->type !== 'text')
                                <span class="kb-chat-media-label">{{ ucfirst(str_replace('_',' ', $message->type)) }} message</span>
                            @endif
                            @if(filled($message->text))
                                <p>{{ $message->text }}</p>
                            @elseif($message->type === 'text')
                                <p>Empty message</p>
                            @endif
                        </div>
                        <time class="studio-message-time" title="{{ $outgoing ? 'Sent by '.($message->admin?->name ?? 'Admin') : 'Incoming Telegram message' }}">{{ $sentAt?->format('g:i A') }}</time>
                    </div>
                    @if($outgoing)
                        <span class="studio-avatar studio-avatar-sm studio-agent-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($message->admin?->name ?? 'KO',0,2)) }}</span>
                    @endif
                </article>
            @empty
                <div class="kb-chat-no-messages">
                    <span class="kb-chat-no-messages-icon" aria-hidden="true">✉</span>
                    <strong>No messages yet</strong>
                    <p>Real Telegram messages will appear here.</p>
                </div>
            @endforelse
        </div>
        @if(auth()->user()->can('message', $user))
            @if($editableTabs ?? false)
                <div class="studio-quick-replies" aria-label="Quick reply templates">
                    <button type="button" wire:click="useQuickReply('payment')">Payment issue</button>
                    <button type="button" wire:click="useQuickReply('bonus')">Bonus info</button>
                    <button type="button" wire:click="useQuickReply('account')">Account help</button>
                    <button type="button" wire:click="useQuickReply('withdrawal')">Withdrawal</button>
                </div>
            @endif
            <form class="kb-chat-composer studio-composer" wire:submit="submitReply" x-data x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.submitReply(); }">
                <button type="button" class="studio-composer-icon" disabled aria-label="File attachments are not available yet" title="Attachments are not available yet">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" width="19" height="19" aria-hidden="true"><path d="m20 12-8.5 8.5a6 6 0 0 1-8.5-8.5L13 2a4 4 0 0 1 5.7 5.7L8.7 17.7a2 2 0 0 1-2.8-2.8l9-9"/></svg>
                </button>
                <button type="button" class="studio-composer-icon" wire:click="appendEmoji" {{ $user->blocked_bot ? 'disabled' : '' }} aria-label="Add emoji">☺</button>
                <label class="sr-only" for="kb-chat-reply-{{ $user->id }}">Type a message</label>
                <textarea id="kb-chat-reply-{{ $user->id }}" wire:model="reply" rows="1" maxlength="4000"
                    placeholder="{{ $user->blocked_bot ? 'This person blocked the bot' : 'Type a message…' }}" {{ $user->blocked_bot ? 'disabled' : '' }}></textarea>
                <button type="submit" class="kb-chat-send studio-send-button" wire:loading.attr="disabled" wire:target="submitReply"
                    {{ $user->blocked_bot ? 'disabled' : '' }} aria-label="Send message">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="20" height="20" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                </button>
            </form>
            @error('reply') <p class="kb-chat-validation" role="alert">{{ $message }}</p> @enderror
            <span class="studio-delivery-hint">Enter to send · replies appear after Telegram confirms delivery</span>
        @else
            <div class="kb-chat-readonly">Read-only access: you can view conversations but cannot send replies.</div>
        @endif
    </div>
@else
    <div class="kb-chat-empty-panel studio-empty-thread"><div class="kb-chat-empty-symbol">✉</div><h3>Select a conversation</h3><p>Choose a person on the left to open their chat history.</p></div>
@endif
