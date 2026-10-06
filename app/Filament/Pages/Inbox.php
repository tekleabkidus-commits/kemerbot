<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class Inbox extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.table-page';

    protected static string|\UnitEnum|null $navigationGroup = 'Audience';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    public function table(Table $table): Table
    {
        return $table->query(User::query()->where('support_status', '!=', 'closed'))->columns([
            TextColumn::make('first_name')->label('Person')->searchable()->description(fn ($r) => $r->username ? '@'.$r->username : 'Telegram member'),
            TextColumn::make('support_status')->badge()->label('Conversation'),
            TextColumn::make('last_active_at')->label('Last activity')->since(),
            TextColumn::make('support_note')->label('Internal note')->limit(50),
        ])->recordUrl(fn ($r) => UserResource::getUrl('view', ['record' => $r]))->recordActions([
            Action::make('resolve')->visible(fn () => ! auth()->user()->isViewer())->action(function ($record) {
                abort_if(auth()->user()->isViewer(), 403);
                $record->update(['support_status' => 'closed']);
                app(AuditLogger::class)->log('support.resolved', $record);
            }),
        ])->emptyStateHeading('You’re all caught up')->emptyStateDescription('Messages that need a personal reply will appear here.')->defaultSort('last_active_at', 'desc');
    }
}
