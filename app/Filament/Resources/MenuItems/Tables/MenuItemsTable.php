<?php

namespace App\Filament\Resources\MenuItems\Tables;

use App\Enums\BotLanguage;
use App\Enums\MenuActionType;
use App\Models\MenuItem;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Tree editing (spec §5.2): rows are scoped per level via the Parent filter
 * (default: main menu) and drag-reorderable within that level. Duplicate
 * copies an item + translations; the preview action shows the Telegram look.
 */
class MenuItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Label (EN)')
                    ->state(fn (MenuItem $record): string => $record->translations
                        ->firstWhere('lang', BotLanguage::En)?->label ?? "Item #{$record->id}"),
                TextColumn::make('action_type')
                    ->label('Action')
                    ->badge()
                    ->color(fn (MenuActionType $state): string => match ($state) {
                        MenuActionType::Reply => 'success',
                        MenuActionType::Submenu => 'info',
                        MenuActionType::Url => 'warning',
                        MenuActionType::Webapp => 'primary',
                    }),
                TextColumn::make('children_count')
                    ->label('Children')
                    ->counts('children')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('position')->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->disabled(fn (): bool => auth()->user()->isViewer()),
            ])
            ->filters([
                SelectFilter::make('parent_id')
                    ->label('Level')
                    ->placeholder('Main menu')
                    ->options(fn (): array => MenuItem::query()
                        ->where('action_type', MenuActionType::Submenu)
                        ->with('translations')
                        ->get()
                        ->mapWithKeys(fn (MenuItem $item) => [
                            $item->id => 'Inside: '.($item->translations->firstWhere('lang', BotLanguage::En)?->label ?? "#{$item->id}"),
                        ])
                        ->all())
                    ->query(fn ($query, array $data) => $data['value'] === null
                        ? $query->whereNull('parent_id')
                        : $query->where('parent_id', $data['value'])),
            ])
            ->reorderable('position')
            ->defaultSort('position')
            ->emptyStateHeading('No menu items yet')
            ->emptyStateDescription('Build the main menu your users see after /start.')
            ->recordActions([
                EditAction::make(),
                Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->authorize(fn (): bool => auth()->user()->can('create', MenuItem::class))
                    ->action(function (MenuItem $record): void {
                        $copy = $record->replicate();
                        $copy->is_active = false;
                        $copy->save();

                        foreach ($record->translations as $translation) {
                            $copy->translations()->create($translation->only(['lang', 'label', 'reply_text']));
                        }
                    }),
                DeleteAction::make(),
            ]);
    }
}
