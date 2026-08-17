<?php

namespace App\Filament\Resources\Polls\Tables;

use App\Models\Poll;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PollsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')
                    ->state(fn (Poll $record): string => $record->question['en'] ?? '?')
                    ->limit(60),
                TextColumn::make('broadcast_id')->label('Broadcast #'),
                TextColumn::make('sent')
                    ->label('Sent to')
                    ->state(fn (Poll $record): string => number_format($record->instances()->count())),
                TextColumn::make('votes')
                    ->state(fn (Poll $record): string => number_format(array_sum(array_map('intval', (array) $record->answer_counts)))),
                IconColumn::make('is_anonymous')->label('Anonymous')->boolean(),
                TextColumn::make('created_at')
                    ->dateTime('d M Y', timezone: config('app.display_timezone'))
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No polls yet')
            ->emptyStateDescription('Create a poll campaign from the broadcast wizard.')
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
