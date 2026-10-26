<?php

namespace App\Filament\Resources\Automations\Pages;

use App\Filament\Resources\Automations\AutomationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Widgets\AutomationListStats;

class ListAutomations extends ListRecords
{
    protected static string $resource = AutomationResource::class;

    public function getSubheading(): ?string
    {
        return 'Design scheduled journeys, manage triggers and inspect active automations for your audience.';
    }

    protected function getHeaderWidgets(): array
    {
        return [AutomationListStats::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
