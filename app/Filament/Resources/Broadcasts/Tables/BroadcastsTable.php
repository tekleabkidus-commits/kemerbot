<?php

namespace App\Filament\Resources\Broadcasts\Tables;

use App\Enums\BroadcastStatus;
use App\Enums\BroadcastType;
use App\Models\Broadcast;
use App\Services\Broadcasts\BroadcastLifecycle;
use App\Services\Broadcasts\BroadcastTestSender;
use App\Services\Broadcasts\InvalidBroadcastTransition;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BroadcastsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (BroadcastType $state): string => match ($state) {
                        BroadcastType::Standard => 'Standard',
                        BroadcastType::MatchCard => 'Match promo',
                        BroadcastType::Poll => 'Poll',
                    }),
                TextColumn::make('translations.text')
                    ->label('Message')
                    ->limit(40)
                    ->listWithLineBreaks()
                    ->limitList(1),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (BroadcastStatus $state): string => match ($state) {
                        BroadcastStatus::Draft => 'gray',
                        BroadcastStatus::Scheduled => 'info',
                        BroadcastStatus::Preparing, BroadcastStatus::Sending => 'warning',
                        BroadcastStatus::Completed => 'success',
                        BroadcastStatus::Paused => 'warning',
                        BroadcastStatus::Cancelled, BroadcastStatus::Failed => 'danger',
                    })
                    ->description(fn (Broadcast $record): ?string => $record->status === BroadcastStatus::Cancelled
                        ? "Sent before cancellation: {$record->sent}"
                        : null),
                TextColumn::make('recurrence')
                    ->label('Recurring')
                    ->formatStateUsing(fn (?array $state): string => $state === null ? '—' : ($state['frequency'] ?? '?'))
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),
                TextColumn::make('scheduled_at')
                    ->label('Scheduled (Addis)')
                    ->dateTime('d M Y · H:i', timezone: config('app.display_timezone'))
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('queued')->numeric()->label('Queued'),
                TextColumn::make('sent')->numeric()->label('Sent'),
                TextColumn::make('blocked')->numeric()->label('Blocked'),
                TextColumn::make('failed')->numeric()->label('Failed'),
                TextColumn::make('clicks_count')
                    ->label('Clicks')
                    ->counts('clicks'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y', timezone: config('app.display_timezone'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(array_combine(
                        array_map(fn (BroadcastStatus $s) => $s->value, BroadcastStatus::cases()),
                        array_map(fn (BroadcastStatus $s) => ucfirst($s->value), BroadcastStatus::cases()),
                    )),
                SelectFilter::make('type')
                    ->options(['standard' => 'Standard', 'match_card' => 'Match promo', 'poll' => 'Poll']),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No campaigns yet')
            ->emptyStateDescription('Create your first Telegram campaign with the 7-step wizard.')
            ->recordActions([
                Action::make('send_now')
                    ->label('Send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (Broadcast $record): bool => in_array($record->status, [BroadcastStatus::Draft, BroadcastStatus::Scheduled], true)
                        && auth()->user()->can('send', $record))
                    ->requiresConfirmation()
                    ->modalDescription(fn (Broadcast $record): string => 'This starts sending to the selected audience immediately. This cannot be undone once messages are delivered.')
                    ->action(function (Broadcast $record): void {
                        try {
                            app(BroadcastLifecycle::class)->start($record);
                            Notification::make()->title('Broadcast started')->success()->send();
                        } catch (InvalidBroadcastTransition $e) {
                            Notification::make()->title('Already started')->body('This broadcast was already picked up — double sends are prevented.')->warning()->send();
                        }
                    }),
                Action::make('test_send')
                    ->label('Test')
                    ->icon('heroicon-o-beaker')
                    ->visible(fn (Broadcast $record): bool => ! $record->status->isTerminal()
                        && auth()->user()->can('send', $record))
                    ->action(function (Broadcast $record): void {
                        $result = app(BroadcastTestSender::class)->send($record);
                        Notification::make()
                            ->title($result['recipients'] === 0
                                ? 'No test recipients configured (Administration → Settings)'
                                : "Test sent to {$result['sent']} of {$result['recipients']} recipients")
                            ->status($result['recipients'] === 0 ? 'warning' : 'success')
                            ->send();
                    }),
                Action::make('cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Broadcast $record): bool => in_array($record->status, [
                        BroadcastStatus::Scheduled, BroadcastStatus::Preparing,
                        BroadcastStatus::Sending, BroadcastStatus::Paused,
                    ], true) && auth()->user()->can('send', $record))
                    ->requiresConfirmation()
                    ->modalDescription('Stops queueing immediately. Messages already accepted by Telegram cannot be recalled.')
                    ->action(function (Broadcast $record): void {
                        app(BroadcastLifecycle::class)->cancel($record);
                        Notification::make()->title("Cancelled — sent before cancellation: {$record->refresh()->sent}")->success()->send();
                    }),
                Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->visible(fn (Broadcast $record): bool => auth()->user()->can('create', Broadcast::class))
                    ->action(function (Broadcast $record): void {
                        app(BroadcastLifecycle::class)->duplicate($record);
                        Notification::make()->title('Duplicated as a new draft')->success()->send();
                    }),
                EditAction::make()
                    ->visible(fn (Broadcast $record): bool => in_array($record->status, [BroadcastStatus::Draft, BroadcastStatus::Scheduled], true)),
                DeleteAction::make()
                    ->visible(fn (Broadcast $record): bool => $record->status === BroadcastStatus::Draft),
            ]);
    }
}
