<?php

namespace App\Filament\Resources\Automations\Schemas;

use App\Enums\AutomationTrigger;
use Closure;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AutomationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Journey')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        Select::make('trigger')
                            ->options([
                                AutomationTrigger::UserJoined->value => 'User joined — welcome drip',
                                AutomationTrigger::Inactive->value => 'Inactive — re-engagement',
                            ])
                            ->required()
                            ->live()
                            ->native(false),
                        TextInput::make('trigger_config.inactive_days')
                            ->label('Inactive after (days)')
                            ->numeric()
                            ->minValue(1)
                            ->default(14)
                            ->visible(fn (Get $get): bool => $get('trigger') === AutomationTrigger::Inactive->value)
                            ->required(fn (Get $get): bool => $get('trigger') === AutomationTrigger::Inactive->value),
                        TextInput::make('cooldown_days')
                            ->label('Cooldown (days)')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('How long before a user may re-enter this journey.')
                            ->rule(fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                if ($get('trigger') === AutomationTrigger::Inactive->value && (int) $value < 1) {
                                    $fail('Re-engagement journeys need a cooldown of at least 1 day to avoid re-spamming inactive users.');
                                }
                            }),
                        Toggle::make('trigger_config.exit_on_conversion')->label('Finish when the user converts')->default(true),
                        Toggle::make('trigger_config.exit_on_activity')->label('Finish when the user returns')->default(false),
                        Select::make('trigger_config.topic')->label('Topic')->options(['general' => 'General', 'matches' => 'Matches', 'offers' => 'Offers', 'news' => 'News'])->default('general'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(false)
                            ->inline(false)
                            ->helperText('Pausing freezes enrollments in place; resuming continues them (nothing is discarded).'),
                    ]),
                Repeater::make('steps_data')
                    ->label('Steps')
                    ->schema([
                        TextInput::make('delay_hours')
                            ->label('Wait (hours)')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->helperText('Counted from the PREVIOUS step; the first step counts from the trigger.'),
                        Textarea::make('text_en')
                            ->label('Message (EN)')
                            ->rows(3)
                            ->required()
                            ->maxLength(4000),
                        Textarea::make('text_am')
                            ->label('Message (AM)')
                            ->rows(3)
                            ->maxLength(4000)
                            ->helperText('Optional — falls back to English.'),
                        Repeater::make('buttons')
                            ->label('URL buttons')
                            ->schema([
                                Hidden::make('kind')->default('url'),
                                TextInput::make('url')->url()->required(),
                                TextInput::make('label.en')->label('Label (EN)')->required()->maxLength(64),
                                TextInput::make('label.am')->label('Label (AM)')->maxLength(64),
                            ])
                            ->columns(3)
                            ->defaultItems(0),
                    ])
                    ->minItems(1)
                    ->reorderable()
                    ->addActionLabel('Add step')
                    // Decision 2: welcome drips must not send an instant second welcome.
                    ->rule(fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                        if ($get('trigger') === AutomationTrigger::UserJoined->value
                            && (int) (($value[array_key_first($value ?? [])] ?? [])['delay_hours'] ?? 0) < 1) {
                            $fail('The first step of a welcome drip must wait at least 1 hour — the instant welcome is the /start reply.');
                        }
                    }),
            ]);
    }
}
