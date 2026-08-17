<?php

namespace App\Filament\Resources\TrackingLinks;

use App\Filament\Resources\TrackingLinks\Pages\CreateTrackingLink;
use App\Filament\Resources\TrackingLinks\Pages\EditTrackingLink;
use App\Filament\Resources\TrackingLinks\Pages\ListTrackingLinks;
use App\Filament\Resources\TrackingLinks\Schemas\TrackingLinkForm;
use App\Filament\Resources\TrackingLinks\Tables\TrackingLinksTable;
use App\Models\TrackingLink;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TrackingLinkResource extends Resource
{
    protected static ?string $model = TrackingLink::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = 'Audience';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return TrackingLinkForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrackingLinksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrackingLinks::route('/'),
            'create' => CreateTrackingLink::route('/create'),
            'edit' => EditTrackingLink::route('/{record}/edit'),
        ];
    }
}
