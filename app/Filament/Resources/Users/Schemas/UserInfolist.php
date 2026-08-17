<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\MessageDirection;
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
                Section::make('Engagement')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('button_clicks')
                            ->label('Broadcast button clicks')
                            ->state(fn ($record): string => number_format($record->buttonClicks()->count())),
                        TextEntry::make('referrals')
                            ->label('Users referred')
                            ->state(fn ($record): string => number_format($record->referrals()->count())),
                        TextEntry::make('automations')
                            ->label('Automation journeys')
                            ->state(function ($record): string {
                                $states = $record->automationStates()->with('automation')->get();

                                if ($states->isEmpty()) {
                                    return 'none';
                                }

                                return $states
                                    ->map(fn ($s) => ($s->automation?->name ?? '?')." — {$s->status->value} (step {$s->current_step_no})")
                                    ->implode(' · ');
                            }),
                        TextEntry::make('polls_received')
                            ->label('Polls received')
                            ->state(fn ($record): string => number_format($record->pollInstances()->count())),
                    ]),
                Section::make('Conversation')
                    ->description('Recent inbound messages and admin replies (pruned after '.config('telegram.message_retention_days').' days).')
                    ->schema([
                        TextEntry::make('conversation')
                            ->hiddenLabel()
                            ->state(function ($record): string {
                                $messages = $record->telegramMessages()
                                    ->latest('sent_at')
                                    ->limit(15)
                                    ->get()
                                    ->reverse();

                                if ($messages->isEmpty()) {
                                    return 'No messages yet.';
                                }

                                $tz = config('app.display_timezone');

                                return $messages->map(function ($m) use ($tz): string {
                                    $who = $m->direction === MessageDirection::Inbound
                                        ? '👤 User'
                                        : '🛠 '.($m->admin?->name ?? 'Admin');
                                    $when = $m->sent_at->timezone($tz)->format('d M H:i');
                                    $body = $m->text ?? "[{$m->type}]";

                                    return "{$when} · {$who}: {$body}";
                                })->implode("\n");
                            }),
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
