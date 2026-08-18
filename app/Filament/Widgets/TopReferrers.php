<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Referral-lite leaderboard (spec §5.6): counts only, no rewards. */
class TopReferrers extends TableWidget
{
    protected static ?int $sort = 8;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Top referrers')
            ->query(
                // whereHas, not HAVING on the alias — Postgres rejects
                // select-list aliases in HAVING (42703).
                User::query()
                    ->withCount('referrals')
                    ->whereHas('referrals')
                    ->orderByDesc('referrals_count')
                    ->limit(10),
            )
            ->columns([
                TextColumn::make('first_name')->label('Referrer'),
                TextColumn::make('username')->placeholder('—')
                    ->formatStateUsing(fn (string $state): string => '@'.$state),
                TextColumn::make('referrals_count')->label('Referrals')->badge()->color('success'),
                TextColumn::make('joined_at')
                    ->dateTime('d M Y', timezone: config('app.display_timezone'))
                    ->label('Joined'),
                TextColumn::make('last_active_at')->label('Last active')->since()->placeholder('never'),
            ])
            ->paginated(false)
            ->emptyStateHeading('No referrals yet')
            ->emptyStateDescription('Add an "Invite friends" menu item so users can share their personal link.');
    }
}
