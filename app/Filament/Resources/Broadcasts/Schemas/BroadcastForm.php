<?php

namespace App\Filament\Resources\Broadcasts\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Edit schema for DRAFT/SCHEDULED broadcasts — the same fields as the wizard,
 * flattened into sections. Sending-related state is managed exclusively by
 * table/page lifecycle actions (BroadcastLifecycle), never edited directly.
 */
class BroadcastForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Type')->schema(BroadcastWizard::typeStep()),
                Section::make('Content')->schema(BroadcastWizard::contentStep()),
                Section::make('Buttons')->schema(BroadcastWizard::buttonsStep()),
                Section::make('Audience')->schema(BroadcastWizard::audienceStep()),
                Section::make('Timing')->schema(BroadcastWizard::timingStep()),
            ]);
    }
}
