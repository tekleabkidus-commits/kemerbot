<?php

namespace App\Filament\Resources\Broadcasts\Pages;

use App\Filament\Resources\Broadcasts\BroadcastResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Widgets\CampaignListStats;

class ListBroadcasts extends ListRecords
{
    protected static string $resource = BroadcastResource::class;

    public function getSubheading(): ?string
    {
        return 'Create, schedule and monitor Telegram campaigns. Delivery counts and statuses reflect your actual audience.';
    }

    protected function getHeaderWidgets(): array
    {
        return [CampaignListStats::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
