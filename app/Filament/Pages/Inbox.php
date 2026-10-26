<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ManagesConversation;
use App\Models\User;
use Filament\Pages\Page;

class Inbox extends Page
{
    use ManagesConversation;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.inbox';

    protected static string|\UnitEnum|null $navigationGroup = 'Audience';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    public ?int $selectedUserId = null;

    public string $search = '';

    public string $statusFilter = 'active';

    public int $conversationLimit = 80;

    public function mount(): void
    {
        $this->selectedUserId = User::query()
            ->whereIn('support_status', ['open', 'pending'])
            ->orderByDesc('last_active_at')
            ->value('id');
    }

    public function selectConversation(int $userId): void
    {
        // Validate the id against the permitted audience before retaining it.
        $user = User::query()->findOrFail($userId);
        abort_unless(auth()->user()?->can('view', $user), 403);

        $this->selectedUserId = $user->id;
        $this->messageLimit = 60;
        $this->reset('reply');
    }

    public function backToList(): void
    {
        $this->selectedUserId = null;
    }

    public function showMoreConversations(): void
    {
        $this->conversationLimit = min(500, $this->conversationLimit + 80);
    }

    protected function conversationUser(): ?User
    {
        return $this->selectedUserId === null
            ? null
            : User::query()->find($this->selectedUserId);
    }

    protected function getViewData(): array
    {
        $query = User::query()
            ->with(['telegramMessages' => fn ($query) => $query
                ->orderByDesc('sent_at')
                ->orderByDesc('id')
                ->limit(1)]);

        if ($this->statusFilter === 'active') {
            $query->whereIn('support_status', ['open', 'pending']);
        } elseif ($this->statusFilter === 'closed') {
            $query->where('support_status', 'closed');
        }

        $search = trim($this->search);

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('first_name', 'ilike', '%'.$search.'%')
                    ->orWhere('username', 'ilike', '%'.$search.'%');

                if (ctype_digit($search)) {
                    $query->orWhere('tg_chat_id', $search);
                }
            });
        }

        $people = $query->orderByDesc('last_active_at')
            ->orderByDesc('id')
            ->limit($this->conversationLimit)
            ->get();

        $user = $this->conversationUser();

        return [
            'people' => $people,
            'selectedUser' => $user,
            'messages' => $this->conversationMessages($user),
        ];
    }
}
