<?php

namespace App\Filament\Resources\Admins\Schemas;

use App\Enums\AdminRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AdminForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->minLength(10)
                    ->helperText('Minimum 10 characters. Leave blank on edit to keep the current password.'),
                Select::make('role')
                    ->options([
                        AdminRole::Owner->value => 'Owner — full access',
                        AdminRole::Marketer->value => 'Marketer — content, broadcasts, audience',
                        AdminRole::Viewer->value => 'Viewer — read-only',
                    ])
                    ->required()
                    ->native(false),
            ]);
    }
}
