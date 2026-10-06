<?php

namespace App\Filament\Resources\Broadcasts\Tables;

use App\Enums\BroadcastStatus;
use App\Enums\BroadcastType;
use App\Models\Broadcast;
use App\Services\AuditLogger;
use App\Services\Broadcasts\BroadcastLifecycle;
use App\Services\Broadcasts\BroadcastTestSender;
use App\Services\Broadcasts\CampaignValidator;
use App\Services\Broadcasts\InvalidBroadcastTransition;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class BroadcastsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Campaign')->placeholder('Untitled campaign')->searchable()->description(fn (Broadcast $record) => '#'.$record->id),
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
                    ->toggleable(isToggledHiddenByDefault: true)->placeholder('—'),
                TextColumn::make('scheduled_at')
                    ->label('Scheduled (Addis)')
                    ->dateTime('d M Y · H:i', timezone: config('app.display_timezone'))
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('queued')->numeric()->label('Queued')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sent')->numeric()->label('Sent'),
                TextColumn::make('blocked')->numeric()->label('Blocked')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('failed')->numeric()->label('Failed')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('skipped')->numeric()->label('Skipped')->toggleable(isToggledHiddenByDefault: true),
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
            ->emptyStateDescription('Share something useful in three simple steps.')
            ->recordActions([
                Action::make('approve')->label('Approve')->icon('heroicon-o-check-badge')
                    ->visible(fn (Broadcast $record) => auth()->user()->isOwner() && ! $record->approved_at && in_array($record->status, [BroadcastStatus::Draft, BroadcastStatus::Scheduled], true))
                    ->requiresConfirmation()->modalDescription('Approve the current message and audience. Any content edit removes approval.')
                    ->action(function (Broadcast $record) {
                        abort_unless(auth()->user()->isOwner(), 403);
                        DB::transaction(function () use ($record) {
                            $record = Broadcast::query()->lockForUpdate()->findOrFail($record->id);
                            abort_unless(in_array($record->status, [BroadcastStatus::Draft, BroadcastStatus::Scheduled], true), 409);
                            app(CampaignValidator::class)->validate($record);
                            $record->update(['approved_at' => now(), 'approved_by' => auth()->id()]);
                            app(AuditLogger::class)->log('campaign.approved', $record);
                        });
                    }),
                Action::make('pause')->visible(fn (Broadcast $record) => $record->status === BroadcastStatus::Sending && auth()->user()->can('send', $record))->action(fn (Broadcast $record) => app(BroadcastLifecycle::class)->pause($record)),
                Action::make('resume')->visible(fn (Broadcast $record) => $record->status === BroadcastStatus::Paused && auth()->user()->can('send', $record))->action(fn (Broadcast $record) => app(BroadcastLifecycle::class)->resume($record)),
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
                            Notification::make()->title('Campaign needs attention')->body($e->getMessage())->warning()->send();
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
