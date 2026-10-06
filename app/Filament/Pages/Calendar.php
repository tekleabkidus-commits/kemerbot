<?php

namespace App\Filament\Pages;

use App\Models\Broadcast;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class Calendar extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.table-page';

    protected static string|\UnitEnum|null $navigationGroup = 'Campaigns';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    public function table(Table $table): Table
    {
        return $table->query(Broadcast::where('status', 'scheduled'))->columns([
            TextColumn::make('name')->placeholder('Untitled campaign')->searchable(),
            TextColumn::make('scheduled_at')->label('Send time (Addis)')->dateTime('D, d M · H:i', timezone: config('app.display_timezone'))->sortable(),
            TextColumn::make('topic')->badge(),
            TextColumn::make('recurrence.frequency')->label('Repeats')->placeholder('One-time'),
            TextColumn::make('approved_at')->label('Approval')->formatStateUsing(fn ($state) => $state ? 'Approved' : 'Needs review')->placeholder('Needs review'),
        ])->defaultSort('scheduled_at')->emptyStateHeading('Your calendar is clear')->emptyStateDescription('Scheduled campaigns appear here in the order they’ll go out.');
    }
}
