<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ManagesConversation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\StudioMetrics;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class Inbox extends Page
{
    use ManagesConversation;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.inbox';

    protected static string|\UnitEnum|null $navigationGroup = 'Audience';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    public ?int $selectedUserId = null;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $profileTab = 'profile';

    public bool $mobileProfile = false;

    public string $noteDraft = '';

    public bool $editingNote = false;

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
        $user = User::findOrFail($userId);
        abort_unless(auth()->user()?->can('view', $user), 403);

        $this->selectedUserId = $user->id;
        $this->messageLimit = 60;
        $this->profileTab = 'profile';
        $this->mobileProfile = false;
        $this->noteDraft = '';
        $this->editingNote = false;
        $this->reset('reply');
    }

    public function backToList(): void
    {
        $this->selectedUserId = null;
        $this->mobileProfile = false;
    }

    public function showMobileProfile(): void
    {
        $this->mobileProfile = true;
    }

    public function showMobileChat(): void
    {
        $this->mobileProfile = false;
    }

    public function changeProfileTab(string $tab): void
    {
        abort_unless(in_array($tab, ['profile', 'activity', 'campaigns'], true), 422);
        $this->profileTab = $tab;
    }

    public function showMoreConversations(): void
    {
        $this->conversationLimit = min(500, $this->conversationLimit + 80);
    }

    public function startNote(): void
    {
        $user = $this->conversationUser();
        abort_unless($user !== null && ! auth()->user()->isViewer(), 403);
        $this->noteDraft = $user->support_note ?? '';
        $this->editingNote = true;
    }

    public function saveNote(): void
    {
        $user = $this->conversationUser();
        abort_unless($user !== null && ! auth()->user()->isViewer(), 403);
        $this->validate(['noteDraft' => ['nullable', 'string', 'max:4000']]);
        $user->update(['support_note' => trim($this->noteDraft) ?: null]);
        app(AuditLogger::class)->log('support.note_updated', $user);
        $this->editingNote = false;
        Notification::make()->title('Internal note saved')->success()->send();
    }

    public function cancelNote(): void
    {
        $this->editingNote = false;
    }

    public function useQuickReply(string $category): void
    {
        $user = $this->conversationUser();
        abort_unless($user && auth()->user()?->can('message', $user), 403);
        $templates = [
            'payment' => 'Thanks for reaching out. Could you please share the payment method, transaction reference and the approximate time? We will look into it.',
            'bonus' => 'I can help with your bonus question. Could you tell me which promotion you are asking about?',
            'account' => 'I can help with your account. Could you describe the issue? Please do not share passwords or verification codes.',
            'withdrawal' => 'I can check your withdrawal question. Which withdrawal method did you use, and approximately when did you submit the request?',
        ];
        abort_unless(array_key_exists($category, $templates), 422);
        $this->reply = $templates[$category];
    }

    protected function conversationUser(): ?User
    {
        return $this->selectedUserId === null ? null : User::find($this->selectedUserId);
    }

    protected function getViewData(): array
    {
        $query = User::query()->with([
            'telegramMessages' => fn ($q) => $q->orderByDesc('sent_at')->orderByDesc('id')->limit(1),
        ]);

        if ($this->statusFilter === 'active') {
            $query->whereIn('support_status', ['open', 'pending']);
        } elseif ($this->statusFilter === 'closed') {
            $query->where('support_status', 'closed');
        }

        $search = trim($this->search);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('first_name', 'ilike', '%'.$search.'%')
                    ->orWhere('username', 'ilike', '%'.$search.'%');

                if (ctype_digit($search)) {
                    $q->orWhere('tg_chat_id', $search);
                }
            });
        }

        $people = $query->orderByDesc('last_active_at')->orderByDesc('id')->limit($this->conversationLimit)->get();
        $ids = $people->pluck('id')->all();

        // Count real unanswered inbound messages rather than inventing unread indicators.
        $lastReply = DB::table('telegram_messages')
            ->whereIn('user_id', $ids)
            ->where('direction', 'admin_outbound')
            ->select('user_id')
            ->selectRaw('max(sent_at) as latest_reply')
            ->groupBy('user_id');

        $awaitingReply = DB::table('telegram_messages as inbound')
            ->leftJoinSub($lastReply, 'last_reply', 'last_reply.user_id', '=', 'inbound.user_id')
            ->whereIn('inbound.user_id', $ids)
            ->where('inbound.direction', 'inbound')
            ->where(fn ($q) => $q->whereNull('last_reply.latest_reply')
                ->orWhereColumn('inbound.sent_at', '>', 'last_reply.latest_reply'))
            ->selectRaw('inbound.user_id, count(*) as total')
            ->groupBy('inbound.user_id')
            ->pluck('total', 'user_id');

        $user = $this->conversationUser();
        $metrics = app(StudioMetrics::class);
        $trend = $metrics->deliveryTrend();

        return [
            'people' => $people,
            'selectedUser' => $user,
            'messages' => $this->conversationMessages($user),
            'awaitingReply' => $awaitingReply,
            'studioStats' => $metrics->inbox(),
            'sparkline' => $metrics->sparkline(array_column($trend, 'count')),
            'recentActivity' => $user?->telegramMessages()->orderByDesc('sent_at')->limit(5)->get() ?? collect(),
            'recentCampaigns' => $user ? DB::table('broadcast_recipients as r')
                ->join('broadcasts as b', 'b.id', '=', 'r.broadcast_id')
                ->where('r.user_id', $user->id)
                ->orderByDesc('r.id')
                ->limit(7)
                ->get(['b.name', 'r.status', 'r.sent_at', 'r.broadcast_id']) : collect(),
        ];
    }
}
