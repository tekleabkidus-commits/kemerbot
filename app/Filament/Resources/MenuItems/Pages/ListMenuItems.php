<?php

namespace App\Filament\Resources\MenuItems\Pages;

use App\Enums\BotLanguage;
use App\Filament\Resources\MenuItems\MenuItemResource;
use App\Services\Bot\MenuRenderer;
use App\Services\SettingsService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMenuItems extends ListRecords
{
    protected static string $resource = MenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Telegram preview')
                ->icon('heroicon-o-device-phone-mobile')
                ->modalHeading('Live menu preview')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(function () {
                    $renderer = app(MenuRenderer::class);
                    $welcome = (array) app(SettingsService::class)->get('welcome.message', []);

                    return view('filament.menu-preview', [
                        'keyboards' => [
                            'en' => $renderer->rootKeyboard(BotLanguage::En),
                            'am' => $renderer->rootKeyboard(BotLanguage::Am),
                        ],
                        'welcome' => $welcome,
                    ]);
                }),
            CreateAction::make(),
        ];
    }
}
