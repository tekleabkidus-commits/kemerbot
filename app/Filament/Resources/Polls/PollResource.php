<?php

namespace App\Filament\Resources\Polls;

use App\Filament\Resources\Polls\Pages\ListPolls;
use App\Filament\Resources\Polls\Pages\ViewPoll;
use App\Filament\Resources\Polls\Schemas\PollInfolist;
use App\Filament\Resources\Polls\Tables\PollsTable;
use App\Models\Poll;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** Poll results (read-only) — polls are authored in the broadcast wizard. */
class PollResource extends Resource
{
    protected static ?string $model = Poll::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Engagement';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return PollInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PollsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPolls::route('/'),
            'view' => ViewPoll::route('/{record}'),
        ];
    }
}
