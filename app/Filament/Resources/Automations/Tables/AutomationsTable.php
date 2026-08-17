<?php

namespace App\Filament\Resources\Automations\Tables;

use App\Enums\AutomationTrigger;
use App\Models\Automation;
use App\Services\AuditLogger;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AutomationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('trigger')
                    ->badge()
                    ->formatStateUsing(fn (AutomationTrigger $state): string => $state === AutomationTrigger::UserJoined ? 'Welcome drip' : 'Re-engagement')
                    ->color(fn (AutomationTrigger $state): string => $state === AutomationTrigger::UserJoined ? 'success' : 'info'),
                TextColumn::make('steps_count')->counts('steps')->label('Steps')->badge()->color('gray'),
                TextColumn::make('user_states_count')->label('Enrollments')->badge(),
                TextColumn::make('cooldown_days')->label('Cooldown (d)'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->emptyStateHeading('No automations yet')
            ->emptyStateDescription('Build a welcome or re-engagement journey.')
            ->recordActions([
                Action::make('pause')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->visible(fn (Automation $record): bool => $record->is_active && auth()->user()->can('update', $record))
                    ->requiresConfirmation()
                    ->modalDescription('Enrollments freeze in place — nothing sends, nothing is discarded. Resume continues where each user stopped.')
                    ->action(function (Automation $record): void {
                        $record->update(['is_active' => false]);
                        app(AuditLogger::class)->log('automation.paused', $record);
                        Notification::make()->title('Automation paused')->success()->send();
                    }),
                Action::make('resume')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->visible(fn (Automation $record): bool => ! $record->is_active && auth()->user()->can('update', $record))
                    ->action(function (Automation $record): void {
                        $record->update(['is_active' => true]);
                        app(AuditLogger::class)->log('automation.resumed', $record);
                        Notification::make()->title('Automation resumed — frozen enrollments continue')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
