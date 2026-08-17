<?php

namespace App\Filament\Widgets;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentCampaigns extends TableWidget
{
    protected static ?int $sort = 7;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent campaigns')
            ->query(Broadcast::query()->latest()->limit(5))
            ->columns([
                TextColumn::make('id')->label('#'),
                TextColumn::make('type')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (BroadcastStatus $state): string => match ($state) {
                        BroadcastStatus::Completed => 'success',
                        BroadcastStatus::Cancelled, BroadcastStatus::Failed => 'danger',
                        BroadcastStatus::Sending, BroadcastStatus::Preparing => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('sent')->numeric(),
                TextColumn::make('blocked')->numeric(),
                TextColumn::make('failed')->numeric(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M · H:i', timezone: config('app.display_timezone')),
            ])
            ->paginated(false)
            ->emptyStateHeading('No campaigns yet')
            ->emptyStateDescription('Create your first Telegram campaign from Broadcasts.');
    }
}
