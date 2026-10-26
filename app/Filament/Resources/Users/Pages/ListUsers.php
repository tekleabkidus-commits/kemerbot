<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Widgets\AudienceListStats;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getSubheading(): ?string
    {
        return 'Explore members who have interacted with your Telegram bot, their preferences and recent activity.';
    }

    protected function getHeaderWidgets(): array
    {
        return [AudienceListStats::class];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
