<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('created_at')
                    ->label('When')
                    ->dateTime('d M Y · H:i:s', timezone: config('app.display_timezone')),
                TextEntry::make('admin.name')->label('Actor')->placeholder('(deleted admin)'),
                TextEntry::make('action'),
                TextEntry::make('subject_type')->placeholder('—'),
                TextEntry::make('subject_id')->placeholder('—'),
                TextEntry::make('meta')
                    ->state(fn ($record): string => $record->meta === null
                        ? '—'
                        : json_encode($record->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            ]);
    }
}
