<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('d M Y · H:i:s', timezone: config('app.display_timezone'))
                    ->sortable(),
                TextColumn::make('admin.name')
                    ->label('Actor')
                    ->placeholder('(deleted admin)'),
                TextColumn::make('action')->badge()->searchable(),
                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (?string $state, $record): string => $state === null
                        ? '—'
                        : class_basename($state).' #'.$record->subject_id),
            ])
            ->filters([
                SelectFilter::make('admin_id')
                    ->label('Actor')
                    ->relationship('admin', 'name'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
