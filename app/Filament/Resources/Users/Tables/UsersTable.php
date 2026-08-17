<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tg_chat_id')->label('Chat ID')->searchable()->copyable(),
                TextColumn::make('first_name')->searchable(),
                TextColumn::make('username')->searchable()->placeholder('—')
                    ->formatStateUsing(fn (string $state): string => '@'.$state),
                TextColumn::make('language')->badge()->placeholder('—'),
                TextColumn::make('source')->badge()->color('info')->placeholder('organic'),
                TextColumn::make('joined_at')
                    ->dateTime('d M Y', timezone: config('app.display_timezone'))
                    ->sortable(),
                TextColumn::make('last_active_at')
                    ->label('Last active')
                    ->since()
                    ->sortable(),
                IconColumn::make('in_channel')->label('Channel')->boolean(),
                IconColumn::make('blocked_bot')->label('Blocked')->boolean()
                    ->trueColor('danger')
                    ->falseColor('gray'),
            ])
            ->filters([
                SelectFilter::make('language')
                    ->options(['en' => 'English', 'am' => 'Amharic']),
                SelectFilter::make('source')
                    ->options(fn (): array => User::query()
                        ->whereNotNull('source')
                        ->distinct()
                        ->pluck('source', 'source')
                        ->all()),
                TernaryFilter::make('blocked_bot')->label('Blocked the bot'),
                TernaryFilter::make('in_channel')->label('Channel member'),
                Filter::make('active_7d')
                    ->label('Active in last 7 days')
                    ->query(fn (Builder $query): Builder => $query->where('last_active_at', '>=', now()->subDays(7))),
            ])
            ->defaultSort('joined_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
