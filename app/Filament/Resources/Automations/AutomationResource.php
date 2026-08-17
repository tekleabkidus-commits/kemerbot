<?php

namespace App\Filament\Resources\Automations;

use App\Filament\Resources\Automations\Pages\CreateAutomation;
use App\Filament\Resources\Automations\Pages\EditAutomation;
use App\Filament\Resources\Automations\Pages\ListAutomations;
use App\Filament\Resources\Automations\Schemas\AutomationForm;
use App\Filament\Resources\Automations\Tables\AutomationsTable;
use App\Models\Automation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AutomationResource extends Resource
{
    protected static ?string $model = Automation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Engagement';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return AutomationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AutomationsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('steps.translations')->withCount('userStates');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAutomations::route('/'),
            'create' => CreateAutomation::route('/create'),
            'edit' => EditAutomation::route('/{record}/edit'),
        ];
    }
}
