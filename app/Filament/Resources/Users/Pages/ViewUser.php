<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Jobs\SendDirectMessageJob;
use App\Services\AuditLogger;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send_message')
                ->label('Send message')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->visible(fn (): bool => auth()->user()->can('message', $this->record))
                ->schema([
                    Textarea::make('text')
                        ->label('Message')
                        ->rows(4)
                        ->required()
                        ->maxLength(4000)
                        ->helperText('Sent from the KemerBet bot on the interactive queue.'),
                ])
                ->action(function (array $data): void {
                    if ($this->record->blocked_bot) {
                        Notification::make()
                            ->title('This user has blocked the bot — the message cannot be delivered.')
                            ->warning()
                            ->send();

                        return;
                    }

                    SendDirectMessageJob::dispatch($this->record->id, auth()->id(), $data['text'])
                        ->onQueue(config('telegram.queues.interactive'));

                    app(AuditLogger::class)->log('direct_message.sent', $this->record, [
                        'length' => mb_strlen($data['text']),
                    ]);

                    Notification::make()->title('Message queued for delivery')->success()->send();
                }),
        ];
    }
}
