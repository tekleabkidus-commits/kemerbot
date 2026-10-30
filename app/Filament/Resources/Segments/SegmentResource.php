<?php

declare(strict_types=1);

namespace App\Filament\Resources\Segments;

use App\Models\AudienceSegment;
use App\Services\Broadcasts\AudienceQuery;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SegmentResource extends Resource
{
    protected static ?int $navigationSort = 3;

    protected static ?string $model = AudienceSegment::class;

    protected static ?string $navigationLabel = 'Saved audiences';

    protected static string|\UnitEnum|null $navigationGroup = 'Audience';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bookmark';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Audience details')
                ->description('Save a clear name and an optional description. You can reuse this group when creating campaigns.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Audience name')
                        ->placeholder('e.g. Recently active members')
                        ->required()
                        ->maxLength(200),
                    Textarea::make('description')
                        ->label('Description')
                        ->placeholder('Who should this audience include?')
                        ->rows(3)
                        ->maxLength(500),
                ]),
            Section::make('Matching rules')
                ->description('Only people meeting the criteria are included. Blocked or unsubscribed people are still excluded by the sending policy.')
                ->columns(2)
                ->schema([
                    TextInput::make('filter.active_last_days')
                        ->label('Active within the last N days')
                        ->numeric()
                        ->minValue(1),
                    TextInput::make('filter.inactive_days')
                        ->label('Inactive for at least N days')
                        ->numeric()
                        ->minValue(1),
                    TextInput::make('filter.source')
                        ->label('Acquisition source')
                        ->placeholder('Any source')
                        ->maxLength(100),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')
                ->searchable()
                ->description(fn (AudienceSegment $record) => $record->description),
            TextColumn::make('audience')
                ->state(fn (AudienceSegment $record) => app(AudienceQuery::class)->estimatedCount($record->filter))
                ->numeric()
                ->label('Reachable people'),
            TextColumn::make('updated_at')->since(),
        ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('Find your people')
            ->emptyStateDescription('Save an audience once and reuse it in future campaigns.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageSegments::route('/')];
    }
}
