<?php

namespace App\Filament\Resources\TrackingLinks\Pages;

use App\Filament\Resources\TrackingLinks\TrackingLinkResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrackingLink extends EditRecord
{
    protected static string $resource = TrackingLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => $this->record->clicks_count === 0 && $this->record->joins_count === 0),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Server-side immutability (spec §5.6): once traffic exists the code
        // never changes, whatever the request claims.
        if ($this->record->clicks_count > 0 || $this->record->joins_count > 0) {
            unset($data['code']);
        }

        return $data;
    }
}
