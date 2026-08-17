<?php

namespace App\Filament\Resources\MenuItems\Schemas;

use App\Enums\BotLanguage;
use App\Enums\MenuActionType;
use App\Models\MenuItem;
use App\Services\MediaFileService;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MenuItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Placement')
                    ->columns(2)
                    ->schema([
                        Select::make('parent_id')
                            ->label('Parent submenu')
                            ->placeholder('— main menu —')
                            ->options(fn (?MenuItem $record): array => MenuItem::query()
                                ->where('action_type', MenuActionType::Submenu)
                                ->when($record, fn ($q) => $q->whereKeyNot($record->id))
                                ->with('translations')
                                ->get()
                                ->mapWithKeys(fn (MenuItem $item) => [
                                    $item->id => $item->translations->firstWhere('lang', BotLanguage::En)?->label ?? "Item #{$item->id}",
                                ])
                                ->all())
                            ->rule(fn (?MenuItem $record): Closure => function (string $attribute, $value, Closure $fail) use ($record) {
                                if ($record !== null && $value !== null && $record->wouldCreateCycle((int) $value)) {
                                    $fail('This parent would create a circular menu.');
                                }
                            })
                            ->native(false),
                        TextInput::make('position')
                            ->numeric()
                            ->default(0)
                            ->helperText('Order within its menu. Drag rows on the list to reorder.'),
                        Select::make('action_type')
                            ->label('Action')
                            ->options([
                                MenuActionType::Reply->value => 'Reply — send text/photo',
                                MenuActionType::Submenu->value => 'Submenu — open child items',
                                MenuActionType::Url->value => 'External URL',
                                MenuActionType::Webapp->value => 'Web App (Mini App)',
                                MenuActionType::Invite->value => 'Invite friends — personal referral link',
                            ])
                            ->default(MenuActionType::Reply->value)
                            ->required()
                            ->live()
                            ->native(false),
                        TextInput::make('url')
                            ->label('URL')
                            ->url()
                            ->maxLength(2048)
                            ->visible(fn (Get $get): bool => in_array($get('action_type'), [MenuActionType::Url->value, MenuActionType::Webapp->value], true))
                            ->required(fn (Get $get): bool => in_array($get('action_type'), [MenuActionType::Url->value, MenuActionType::Webapp->value], true)),
                        Toggle::make('is_active')
                            ->default(true)
                            ->inline(false)
                            ->rule(fn (Get $get, ?MenuItem $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                                // Spec §5.2: a submenu needs at least one child to be activated.
                                if ($value
                                    && $get('action_type') === MenuActionType::Submenu->value
                                    && ($record === null || $record->children()->count() === 0)) {
                                    $fail('A submenu needs at least one child item before it can be activated.');
                                }
                            }),
                    ]),
                Tabs::make('Labels & reply')
                    ->tabs([
                        Tab::make('English')
                            ->schema([
                                TextInput::make('label_en')
                                    ->label('Button label (EN)')
                                    ->required()
                                    ->maxLength(64),
                                Textarea::make('reply_text_en')
                                    ->label('Reply text (EN)')
                                    ->rows(4)
                                    ->maxLength(4000)
                                    ->visible(fn (Get $get): bool => $get('action_type') === MenuActionType::Reply->value)
                                    ->required(fn (Get $get): bool => $get('action_type') === MenuActionType::Reply->value),
                            ]),
                        Tab::make('Amharic')
                            ->schema([
                                TextInput::make('label_am')
                                    ->label('Button label (AM)')
                                    ->maxLength(64)
                                    ->helperText('Optional — Amharic users fall back to English if empty.'),
                                Textarea::make('reply_text_am')
                                    ->label('Reply text (AM)')
                                    ->rows(4)
                                    ->maxLength(4000)
                                    ->visible(fn (Get $get): bool => $get('action_type') === MenuActionType::Reply->value),
                            ]),
                    ]),
                Section::make('Media')
                    ->collapsed()
                    ->visible(fn (Get $get): bool => $get('action_type') === MenuActionType::Reply->value)
                    ->schema([
                        FileUpload::make('media_upload')
                            ->label('Reply media (optional)')
                            ->disk(config('filesystems.default'))
                            ->directory('media')
                            ->acceptedFileTypes(MediaFileService::acceptedMimeTypes())
                            ->maxSize(50 * 1024),
                    ]),
            ]);
    }
}
