<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Admin;
use App\Services\AuditLogger;
use App\Services\DirectMessages;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manage_contact')->label('Preferences & support')->icon('heroicon-o-adjustments-horizontal')
                ->visible(fn () => ! auth()->user()->isViewer())->fillForm(fn () => $this->record->toArray())
                ->schema([
                    Toggle::make('marketing_subscribed')->label('Receives promotions'),
                    Select::make('preferred_language')->options(['en' => 'English', 'am' => 'Amharic']),
                    Select::make('topics')->multiple()->options(['general' => 'General', 'matches' => 'Matches', 'offers' => 'Offers', 'news' => 'News']),
                    TextInput::make('daily_message_limit')->numeric()->minValue(1)->maxValue(9)->required(),
                    Select::make('support_status')->options(['open' => 'Needs reply', 'pending' => 'Waiting', 'closed' => 'Resolved'])->required(),
                    Select::make('assigned_admin_id')->label('Assigned to')->options(fn () => Admin::pluck('name', 'id')),
                    Textarea::make('support_note')->label('Internal note')->maxLength(4000),
                ])->action(function (array $data) {
                    abort_if(auth()->user()->isViewer(), 403);
                    $this->record->update($data);
                    app(AuditLogger::class)->log('contact.updated', $this->record, ['fields' => array_keys($data)]);
                }),
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

                    abort_unless(auth()->user()->can('message', $this->record), 403);
                    app(DirectMessages::class)->queue($this->record->id, auth()->id(), $data['text']);

                    app(AuditLogger::class)->log('direct_message.queued', $this->record, [
                        'length' => mb_strlen($data['text']),
                    ]);

                    Notification::make()->title('Message queued for delivery')->success()->send();
                }),
        ];
    }
}
