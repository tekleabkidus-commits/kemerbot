<?php

namespace App\Filament\Resources\KeywordReplies\Tables;

use App\Enums\KeywordMatchType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class KeywordRepliesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('keywords')
                    ->badge()
                    ->separator(','),
                TextColumn::make('match_type')
                    ->badge()
                    ->color(fn (KeywordMatchType $state): string => $state === KeywordMatchType::Exact ? 'success' : 'info'),
                TextColumn::make('position')->sortable(),
                TextColumn::make('translations.reply_text')
                    ->label('Reply')
                    ->limit(60)
                    ->listWithLineBreaks()
                    ->limitList(1),
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->disabled(fn (): bool => auth()->user()->isViewer()),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->defaultSort('position')
            ->emptyStateHeading('No auto replies yet')
            ->emptyStateDescription('Create keyword rules so the bot answers common questions automatically.')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
