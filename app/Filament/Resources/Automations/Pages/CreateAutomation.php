<?php

namespace App\Filament\Resources\Automations\Pages;

use App\Filament\Resources\Automations\AutomationResource;
use App\Filament\Resources\Automations\Support\SavesAutomationSteps;
use Filament\Resources\Pages\CreateRecord;

class CreateAutomation extends CreateRecord
{
    use SavesAutomationSteps;

    protected static string $resource = AutomationResource::class;

    private array $stepsData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->stepsData = $data['steps_data'] ?? [];
        unset($data['steps_data']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->saveSteps($this->record, $this->stepsData);
    }
}
