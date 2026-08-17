<?php

namespace App\Filament\Resources\Automations\Pages;

use App\Filament\Resources\Automations\AutomationResource;
use App\Filament\Resources\Automations\Support\SavesAutomationSteps;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAutomation extends EditRecord
{
    use SavesAutomationSteps;

    protected static string $resource = AutomationResource::class;

    private array $stepsData = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('Deleting an automation also deletes all of its enrollments.'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['steps_data'] = $this->fillStepsData($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->stepsData = $data['steps_data'] ?? [];
        unset($data['steps_data']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->saveSteps($this->record, $this->stepsData);
    }
}
