<?php

namespace App\Filament\Resources\Segments;

use App\Models\AudienceSegment;
use App\Services\Broadcasts\AudienceQuery;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
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
            TextInput::make('name')->required()->maxLength(200), TextInput::make('description')->maxLength(500),
            Select::make('filter.language')->label('Language')->options(['en' => 'English', 'am' => 'Amharic']),
            TextInput::make('filter.active_last_days')->label('Active within days')->numeric()->minValue(1),
            TextInput::make('filter.inactive_days')->label('Inactive for days')->numeric()->minValue(1),
            TextInput::make('filter.source')->label('Acquisition source')->maxLength(100),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->description(fn ($r) => $r->description),
            TextColumn::make('audience')->state(fn ($r) => app(AudienceQuery::class)->estimatedCount($r->filter))->numeric()->label('Reachable people'),
            TextColumn::make('updated_at')->since(),
        ])->recordActions([EditAction::make(), DeleteAction::make()])->emptyStateHeading('Find your people')->emptyStateDescription('Save an audience once and use it in future campaigns.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageSegments::route('/')];
    }
}
