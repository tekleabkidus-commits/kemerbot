<?php

declare(strict_types=1);

namespace App\Filament\Resources\Automations\Pages;

use App\Filament\Resources\Automations\AutomationResource;
use App\Filament\Widgets\AutomationListStats;
use App\Models\Automation;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAutomations extends ListRecords
{
    protected static string $resource = AutomationResource::class;

    protected string $view = 'filament.resources.automations.pages.list-automations';

    public ?int $previewAutomationId = null;

    public function chooseJourney(int $id): void
    {
        $automation = Automation::findOrFail($id);
        abort_unless(auth()->user()?->can('view', $automation), 403);
        $this->previewAutomationId = $id;
    }

    public function getSubheading(): ?string
    {
        return 'Build and manage audience journeys for Telegram.';
    }

    protected function getHeaderWidgets(): array
    {
        return [AutomationListStats::class];
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Create automation')];
    }

    protected function getViewData(): array
    {
        $journeys = Automation::query()->latest()->limit(5)->get();
        $selected = $this->previewAutomationId
            ? Automation::query()->with('steps.translations')->find($this->previewAutomationId)
            : Automation::query()->with('steps.translations')->latest()->first();

        return compact('journeys', 'selected');
    }
}
