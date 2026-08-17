<?php

namespace App\Filament\Resources\TrackingLinks\Pages;

use App\Filament\Resources\TrackingLinks\TrackingLinkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrackingLinks extends ListRecords
{
    protected static string $resource = TrackingLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
