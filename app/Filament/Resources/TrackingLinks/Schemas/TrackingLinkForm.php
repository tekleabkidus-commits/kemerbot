<?php

namespace App\Filament\Resources\TrackingLinks\Schemas;

use App\Models\TrackingLink;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TrackingLinkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->regex(TrackingLink::CODE_PATTERN)
                    ->unique(ignoreRecord: true)
                    ->maxLength(64)
                    // Spec §5.6: immutable once traffic exists — attribution never rewrites.
                    ->disabled(fn (?TrackingLink $record): bool => $record !== null
                        && ($record->clicks_count > 0 || $record->joins_count > 0))
                    ->helperText('Letters, digits, - and _ only. Locked once the link has traffic.'),
                TextInput::make('name')
                    ->label('Campaign name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Renaming never rewrites attribution.'),
                Textarea::make('description')
                    ->rows(2)
                    ->maxLength(1000),
                Toggle::make('is_active')
                    ->default(true)
                    ->inline(false)
                    ->helperText('Inactive links stop redirecting (404) and stop counting joins.'),
            ]);
    }
}
