<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Enums\MessageDirection;
use App\Models\TelegramMessage;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DirectMessages;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Shared conversation actions for the support inbox and the user profile.
 * The persisted transcript contains received messages and successfully sent
 * administrator replies. Pending replies remain in the durable outbox.
 */
trait ManagesConversation
{
    public string $reply = '';

    public int $messageLimit = 60;

    abstract protected function conversationUser(): ?User;

    /** @return Collection<int, TelegramMessage> */
    protected function conversationMessages(?User $user): Collection
    {
        if ($user === null) {
            return collect();
        }

        return $user->telegramMessages()
            ->with('admin')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit($this->messageLimit)
            ->get()
            ->reverse()
            ->values();
    }

    public function loadEarlierMessages(): void
    {
        $this->messageLimit = min(500, $this->messageLimit + 60);
    }

    public function appendEmoji(): void
    {
        $user = $this->conversationUser();
        abort_unless($user !== null && auth()->user()?->can('message', $user), 403);
        $this->reply = mb_substr($this->reply, 0, 3998).' 🙂';
    }

    public function submitReply(): void
    {
        $user = $this->conversationUser();
        abort_unless($user !== null, 404);
        abort_unless(auth()->user()?->can('message', $user), 403);

        $this->validate([
            'reply' => ['required', 'string', 'max:4000'],
        ]);

        $text = trim($this->reply);

        if ($text === '') {
            $this->addError('reply', 'Enter a message before sending.');

            return;
        }

        if ($user->blocked_bot) {
            Notification::make()->title('This person has blocked the bot.')
                ->body('The message was not queued.')->warning()->send();

            return;
        }

        app(DirectMessages::class)->queue($user->id, auth()->id(), $text);

        app(AuditLogger::class)->log('direct_message.queued', $user, [
            'length' => mb_strlen($text),
        ]);

        $this->reset('reply');

        Notification::make()->title('Reply queued')
            ->body('It will appear in the conversation when Telegram accepts it.')
            ->success()->send();
    }

    public function resolveSelectedConversation(): void
    {
        $user = $this->conversationUser();
        abort_unless($user !== null, 404);
        abort_unless(auth()->user()?->can('message', $user), 403);

        $user->update(['support_status' => 'closed']);
        app(AuditLogger::class)->log('support.resolved', $user);

        Notification::make()->title('Conversation resolved')->success()->send();
    }

    public function reopenSelectedConversation(): void
    {
        $user = $this->conversationUser();
        abort_unless($user !== null, 404);
        abort_unless(auth()->user()?->can('message', $user), 403);

        $user->update(['support_status' => 'open']);
        app(AuditLogger::class)->log('support.reopened', $user);

        Notification::make()->title('Conversation reopened')->success()->send();
    }
}
