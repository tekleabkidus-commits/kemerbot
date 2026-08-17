<?php

namespace App\Filament\Resources\Admins\Tables;

use App\Enums\AdminRole;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdminsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (AdminRole $state): string => $state->label())
                    ->color(fn (AdminRole $state): string => match ($state) {
                        AdminRole::Owner => 'danger',
                        AdminRole::Marketer => 'warning',
                        AdminRole::Viewer => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->dateTime(timezone: config('app.display_timezone'))
                    ->label('Created')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
