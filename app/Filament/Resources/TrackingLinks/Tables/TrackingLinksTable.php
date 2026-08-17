<?php

namespace App\Filament\Resources\TrackingLinks\Tables;

use App\Models\TrackingLink;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TrackingLinksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->copyable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('short_link')
                    ->label('Short link')
                    ->state(fn (TrackingLink $record): string => url('/r/'.$record->code))
                    ->copyable()
                    ->copyMessage('Short link copied'),
                TextColumn::make('deep_link')
                    ->label('Telegram deep link')
                    ->state(fn (TrackingLink $record): string => 'https://t.me/'.config('telegram.bot_username').'?start='.$record->code)
                    ->copyable()
                    ->copyMessage('Deep link copied')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('clicks_count')->label('Clicks')->numeric()->sortable(),
                TextColumn::make('joins_count')->label('Joins')->numeric()->sortable(),
                TextColumn::make('conversion')
                    ->label('Conversion')
                    ->state(fn (TrackingLink $record): string => $record->conversionRate() === null
                        ? '—'
                        : $record->conversionRate().'%'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('clicks_count', 'desc')
            ->emptyStateHeading('No tracking links yet')
            ->emptyStateDescription('Create a tracking link to measure where new Telegram users come from.')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (TrackingLink $record): bool => $record->clicks_count === 0 && $record->joins_count === 0),
            ]);
    }
}
