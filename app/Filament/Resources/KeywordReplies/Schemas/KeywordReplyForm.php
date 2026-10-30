<?php

namespace App\Filament\Resources\KeywordReplies\Schemas;

use App\Enums\KeywordMatchType;
use App\Services\MediaFileService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class KeywordReplyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Matching')
                    ->columns(2)
                    ->schema([
                        TagsInput::make('keywords')
                            ->required(fn (Get $get): bool => $get('match_type') !== KeywordMatchType::Any->value)
                            ->visible(fn (Get $get): bool => $get('match_type') !== KeywordMatchType::Any->value)
                            ->helperText('Case-insensitive. Leave this blank for the Any message rule.'),
                        Select::make('match_type')
                            ->options([
                                KeywordMatchType::Exact->value => 'Exact — message equals the keyword',
                                KeywordMatchType::Contains->value => 'Contains — keyword appears in the message',
                                KeywordMatchType::Any->value => 'Any message — one automatic reply when no keyword matches',
                            ])
                            ->default(KeywordMatchType::Exact->value)
                            ->required()
                            ->live()
                            ->native(false),
                        TextInput::make('position')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower position wins on ties.'),
                        Toggle::make('is_active')
                            ->default(true)
                            ->inline(false),
                    ]),
                Section::make('Reply (English)')
                    ->schema([
                        Textarea::make('reply_text_en')
                            ->label('Automatic reply message')
                            ->rows(4)
                            ->required()
                            ->maxLength(4000),
                    ]),
                Section::make('Media & buttons')
                    ->collapsed()
                    ->schema([
                        FileUpload::make('media_upload')
                            ->label('Attached media (optional)')
                            ->disk(config('filesystems.default'))
                            ->directory('media')
                            ->acceptedFileTypes(MediaFileService::acceptedMimeTypes())
                            ->maxSize(50 * 1024)
                            ->helperText('Photo, GIF, or MP4. Replaces the current media if set.'),
                        Repeater::make('buttons')
                            ->label('URL buttons (optional)')
                            ->schema([
                                Hidden::make('kind')->default('url'),
                                TextInput::make('url')->url()->required(),
                                TextInput::make('label.en')->label('Label (EN)')->required()->maxLength(64),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->reorderable(),
                    ]),
            ]);
    }
}
