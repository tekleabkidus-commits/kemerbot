<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Basic profile (spec §15 Batch 3); engagement + conversation land in Batch 4. */
class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $tz = config('app.display_timezone');

        return $schema
            ->components([
                Section::make('Identity')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('tg_chat_id')->label('Telegram chat ID')->copyable(),
                        TextEntry::make('first_name'),
                        TextEntry::make('username')->placeholder('—'),
                        TextEntry::make('language')->placeholder('—'),
                    ]),
                Section::make('Attribution')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('source')->placeholder('organic'),
                        TextEntry::make('referredBy.first_name')
                            ->label('Referred by')
                            ->placeholder('—'),
                    ]),
                Section::make('Activity')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('joined_at')->dateTime('d M Y · H:i', timezone: $tz),
                        TextEntry::make('last_active_at')->since()->placeholder('never'),
                        IconEntry::make('blocked_bot')->label('Blocked the bot')->boolean(),
                        TextEntry::make('blocked_at')->dateTime('d M Y · H:i', timezone: $tz)->placeholder('—'),
                        IconEntry::make('in_channel')->label('In channel')->boolean(),
                        TextEntry::make('channel_checked_at')
                            ->label('Membership last checked')
                            ->since()
                            ->placeholder('never'),
                    ]),
            ]);
    }
}
