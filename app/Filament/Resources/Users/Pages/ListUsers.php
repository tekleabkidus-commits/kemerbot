<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\AudienceListStats;
use App\Models\User;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected string $view = 'filament.resources.users.pages.list-users';

    public ?int $previewUserId = null;

    public function mount(): void
    {
        parent::mount();
        $this->previewUserId = User::query()->orderByDesc('last_active_at')->value('id');
    }

    public function selectPreview(int $userId): void
    {
        $user = User::findOrFail($userId);
        abort_unless(auth()->user()?->can('view', $user), 403);
        $this->previewUserId = $user->id;
    }

    public function getSubheading(): ?string
    {
        return 'Manage your audience and people database.';
    }

    protected function getHeaderWidgets(): array
    {
        return [AudienceListStats::class];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getViewData(): array
    {
        return [
            'previewUsers' => User::query()->orderByDesc('last_active_at')->limit(5)->get(),
            'previewUser' => $this->previewUserId ? User::find($this->previewUserId) : null,
        ];
    }
}
