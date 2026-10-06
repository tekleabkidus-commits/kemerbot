<?php

namespace App\Filament\Resources\Automations\Pages;

use App\Filament\Resources\Automations\AutomationResource;
use App\Filament\Resources\Automations\Support\SavesAutomationSteps;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

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
        if ($this->record->userStates()->where('status', 'active')->exists() && ($data['steps_data'] ?? []) !== $this->fillStepsData($this->record)) {
            throw ValidationException::withMessages(['data.steps_data' => 'This journey has active participants. Keep its steps unchanged, or create a new journey.']);
        }
        $this->stepsData = $data['steps_data'] ?? [];
        unset($data['steps_data']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->saveSteps($this->record, $this->stepsData);
    }
}
