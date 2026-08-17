<?php

namespace App\Filament\Resources\Broadcasts\Schemas;

use App\Enums\BotLanguage;
use App\Filament\Resources\Broadcasts\Support\BroadcastFormState;
use App\Models\User;
use App\Services\Broadcasts\AudienceQuery;
use App\Services\Broadcasts\BroadcastRenderer;
use App\Services\Broadcasts\BroadcastTestSender;
use App\Services\MediaFileService;
use App\Services\SettingsService;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;

/**
 * The 7-step broadcast wizard (spec §5.3): Type → Content → Buttons →
 * Audience → Timing → Test → Review. Also reused piecemeal by the edit form.
 */
class BroadcastWizard
{
    /** @return array<Step> */
    public static function steps(): array
    {
        return [
            Step::make('Type')->schema(self::typeStep()),
            Step::make('Content')->schema(self::contentStep()),
            Step::make('Buttons')->schema(self::buttonsStep()),
            Step::make('Audience')->schema(self::audienceStep()),
            Step::make('Timing')->schema(self::timingStep()),
            Step::make('Test')->schema(self::testStep()),
            Step::make('Review')->schema(self::reviewStep()),
        ];
    }

    /** @return array<mixed> */
    public static function typeStep(): array
    {
        return [
            Radio::make('type')
                ->label('Campaign type')
                ->options([
                    'standard' => 'Standard — text/photo message',
                    'match_card' => 'Match promo — image + structured match caption',
                    'poll' => 'Poll — native Telegram poll',
                ])
                ->default('standard')
                ->required()
                ->live(),
        ];
    }

    /** @return array<mixed> */
    public static function contentStep(): array
    {
        return [
            Section::make('Match details')
                ->visible(fn (Get $get): bool => $get('type') === 'match_card')
                ->columns(2)
                ->schema([
                    TextInput::make('tf_home_team')->label('Home team')
                        ->required(fn (Get $get): bool => $get('type') === 'match_card'),
                    TextInput::make('tf_away_team')->label('Away team')
                        ->required(fn (Get $get): bool => $get('type') === 'match_card'),
                    DateTimePicker::make('tf_kickoff_at')
                        ->label('Kickoff (Addis time)')
                        ->timezone(config('app.display_timezone'))
                        ->seconds(false)
                        ->required(fn (Get $get): bool => $get('type') === 'match_card'),
                    TextInput::make('tf_cta')->label('Call to action')
                        ->default('Bet now: https://kemerbet.co/en/sport')
                        ->placeholder('Bet now: https://kemerbet.co/en/sport'),
                    TextInput::make('tf_odds_home')->label('Odds — home (1)'),
                    TextInput::make('tf_odds_draw')->label('Odds — draw (X)'),
                    TextInput::make('tf_odds_away')->label('Odds — away (2)'),
                ]),
            Section::make('Poll')
                ->visible(fn (Get $get): bool => $get('type') === 'poll')
                ->schema([
                    TextInput::make('poll_question_en')
                        ->label('Question (EN)')
                        ->maxLength(300)
                        ->required(fn (Get $get): bool => $get('type') === 'poll'),
                    TextInput::make('poll_question_am')
                        ->label('Question (AM)')
                        ->maxLength(300)
                        ->helperText('Optional — falls back to English.'),
                    Repeater::make('poll_options')
                        ->label('Options (2–10)')
                        ->schema([
                            TextInput::make('en')->label('Option (EN)')->required()->maxLength(100),
                            TextInput::make('am')->label('Option (AM)')->maxLength(100),
                        ])
                        ->columns(2)
                        ->minItems(2)
                        ->maxItems(10)
                        ->defaultItems(2)
                        ->reorderable()
                        ->required(fn (Get $get): bool => $get('type') === 'poll'),
                    Toggle::make('poll_is_anonymous')
                        ->label('Anonymous poll')
                        ->default(true)
                        ->helperText('Results aggregate on the dashboard either way.'),
                ]),
            Tabs::make('Message')
                ->visible(fn (Get $get): bool => $get('type') !== 'poll')
                ->tabs([
                    Tab::make('English')
                        ->schema([
                            Textarea::make('text_en')
                                ->label('Message (EN)')
                                ->rows(6)
                                ->live(onBlur: true)
                                ->maxLength(4096)
                                ->required(fn (Get $get): bool => $get('type') !== 'poll')
                                ->helperText('Personalization: {first_name}. Telegram limit: 4096 characters — 1024 when media is attached.'),
                            FileUpload::make('media_en_upload')
                                ->label('Media (EN)')
                                ->disk(config('filesystems.default'))
                                ->directory('media')
                                ->acceptedFileTypes(MediaFileService::acceptedMimeTypes())
                                ->maxSize(50 * 1024),
                        ]),
                    Tab::make('Amharic')
                        ->schema([
                            Textarea::make('text_am')
                                ->label('Message (AM)')
                                ->rows(6)
                                ->live(onBlur: true)
                                ->maxLength(4096)
                                ->helperText('Optional — Amharic users fall back to the English message if empty.'),
                            FileUpload::make('media_am_upload')
                                ->label('Media (AM)')
                                ->disk(config('filesystems.default'))
                                ->directory('media')
                                ->acceptedFileTypes(MediaFileService::acceptedMimeTypes())
                                ->maxSize(50 * 1024)
                                ->helperText('Optional — falls back to the English media if empty.'),
                        ]),
                ]),
            Section::make('Preview')
                ->description('How the English rendering will look, personalized with a sample name.')
                ->collapsible()
                ->schema([
                    Text::make(function (Get $get): string {
                        $broadcast = BroadcastFormState::previewBroadcast([
                            'type' => $get('type'),
                            'tf_home_team' => $get('tf_home_team'),
                            'tf_away_team' => $get('tf_away_team'),
                            'tf_kickoff_at' => $get('tf_kickoff_at'),
                            'tf_odds_home' => $get('tf_odds_home'),
                            'tf_odds_draw' => $get('tf_odds_draw'),
                            'tf_odds_away' => $get('tf_odds_away'),
                            'tf_cta' => $get('tf_cta'),
                            'text_en' => $get('text_en'),
                            'text_am' => $get('text_am'),
                            'buttons_data' => [],
                        ]);

                        $sample = new User(['first_name' => 'Abel', 'language' => 'en']);

                        $text = app(BroadcastRenderer::class)
                            ->renderForLanguage($broadcast, BotLanguage::En, $sample)
                            ->text;

                        return filled($text) ? $text : 'Add content to see the preview.';
                    }),
                ]),
        ];
    }

    /** @return array<mixed> */
    public static function buttonsStep(): array
    {
        return [
            Text::make('Native polls carry no inline keyboard — this step is skipped for poll campaigns.')
                ->visible(fn (Get $get): bool => $get('type') === 'poll'),
            Repeater::make('buttons_data')
                ->label('Inline keyboard')
                ->visible(fn (Get $get): bool => $get('type') !== 'poll')
                ->schema([
                    Select::make('kind')
                        ->options(['url' => 'URL — opens a link', 'callback' => 'Callback — tracked tap'])
                        ->default('url')
                        ->required()
                        ->live()
                        ->native(false),
                    TextInput::make('url')
                        ->url()
                        ->visible(fn (Get $get): bool => $get('kind') === 'url')
                        ->required(fn (Get $get): bool => $get('kind') === 'url'),
                    TextInput::make('label.en')->label('Label (EN)')->required()->maxLength(64),
                    TextInput::make('label.am')->label('Label (AM)')->maxLength(64),
                    TextInput::make('row')
                        ->numeric()
                        ->default(0)
                        ->helperText('Buttons with the same row number sit side by side.'),
                ])
                ->columns(2)
                ->defaultItems(0)
                ->reorderable()
                ->addActionLabel('Add button'),
        ];
    }

    /** @return array<mixed> */
    public static function audienceStep(): array
    {
        $estimate = fn (Get $get): string => number_format(
            app(AudienceQuery::class)->estimatedCount(BroadcastFormState::audienceFilter([
                'aud_joined_after' => $get('aud_joined_after'),
                'aud_joined_before' => $get('aud_joined_before'),
                'aud_active_last_days' => $get('aud_active_last_days'),
                'aud_inactive_days' => $get('aud_inactive_days'),
                'aud_language' => $get('aud_language'),
                'aud_source' => $get('aud_source'),
                'aud_in_channel' => $get('aud_in_channel'),
            ]))
        );

        return [
            Section::make('Filters')
                ->description('Leave everything empty to reach everyone. Blocked users are always excluded.')
                ->columns(2)
                ->schema([
                    DatePicker::make('aud_joined_after')->label('Joined after')->live(),
                    DatePicker::make('aud_joined_before')->label('Joined before')->live(),
                    TextInput::make('aud_active_last_days')->label('Active in last N days')->numeric()->minValue(1)->live(onBlur: true),
                    TextInput::make('aud_inactive_days')->label('Inactive for N+ days')->numeric()->minValue(1)->live(onBlur: true),
                    Select::make('aud_language')->label('Language')
                        ->options(['en' => 'English', 'am' => 'Amharic'])
                        ->live()->native(false),
                    Select::make('aud_source')->label('Source')
                        ->options(fn (): array => User::query()->whereNotNull('source')->distinct()->pluck('source', 'source')->all())
                        ->live()->native(false),
                    Select::make('aud_in_channel')->label('Channel membership')
                        ->options(['1' => 'In the channel', '0' => 'Not in the channel'])
                        ->live()->native(false),
                ]),
            Text::make(fn (Get $get): string => 'Estimated audience: '.$estimate($get).' users')
                ->size('lg')
                ->weight('bold'),
            Text::make(fn (Get $get): string => app(AudienceQuery::class)->describe(BroadcastFormState::audienceFilter([
                'aud_joined_after' => $get('aud_joined_after'),
                'aud_joined_before' => $get('aud_joined_before'),
                'aud_active_last_days' => $get('aud_active_last_days'),
                'aud_inactive_days' => $get('aud_inactive_days'),
                'aud_language' => $get('aud_language'),
                'aud_source' => $get('aud_source'),
                'aud_in_channel' => $get('aud_in_channel'),
            ]))),
        ];
    }

    /** @return array<mixed> */
    public static function timingStep(): array
    {
        return [
            Radio::make('timing_mode')
                ->label('When should this go out?')
                ->options([
                    'now' => 'Send now',
                    'scheduled' => 'Schedule for later',
                    'recurring' => 'Recurring campaign',
                ])
                ->default('now')
                ->required()
                ->live(),
            DateTimePicker::make('scheduled_at')
                ->label('Send at (Addis time)')
                ->timezone(config('app.display_timezone'))
                ->seconds(false)
                ->minDate(fn (): CarbonInterface => now())
                ->visible(fn (Get $get): bool => $get('timing_mode') === 'scheduled')
                ->required(fn (Get $get): bool => $get('timing_mode') === 'scheduled')
                ->helperText('Past dates are blocked. Times are Africa/Addis_Ababa.'),
            Section::make('Recurrence')
                ->visible(fn (Get $get): bool => $get('timing_mode') === 'recurring')
                ->columns(3)
                ->schema([
                    Select::make('rec_frequency')
                        ->label('Repeats')
                        ->options(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'])
                        ->live()
                        ->required(fn (Get $get): bool => $get('timing_mode') === 'recurring')
                        ->native(false),
                    Select::make('rec_day')
                        ->label('Day of week')
                        ->options([
                            'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
                            'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday',
                        ])
                        ->visible(fn (Get $get): bool => $get('rec_frequency') === 'weekly')
                        ->required(fn (Get $get): bool => $get('timing_mode') === 'recurring' && $get('rec_frequency') === 'weekly')
                        ->native(false),
                    TextInput::make('rec_day_of_month')
                        ->label('Day of month')
                        ->numeric()->minValue(1)->maxValue(31)
                        ->visible(fn (Get $get): bool => $get('rec_frequency') === 'monthly')
                        ->required(fn (Get $get): bool => $get('timing_mode') === 'recurring' && $get('rec_frequency') === 'monthly'),
                    TimePicker::make('rec_time')
                        ->label('At (Addis time)')
                        ->seconds(false)
                        ->required(fn (Get $get): bool => $get('timing_mode') === 'recurring'),
                ]),
        ];
    }

    /** @return array<mixed> */
    public static function testStep(): array
    {
        return [
            Text::make(function (): string {
                $recipients = (array) app(SettingsService::class)->get('broadcast.test_recipient_chat_ids', []);

                return $recipients === []
                    ? 'No test recipients configured. An Owner can add test recipient chat IDs under Administration → Settings.'
                    : 'Test recipients configured: '.count($recipients).'. The English rendering is sent, prefixed with [TEST].';
            }),
            Actions::make([
                Action::make('send_test')
                    ->label('Send test message')
                    ->icon('heroicon-o-paper-airplane')
                    ->action(function (Get $get): void {
                        $broadcast = BroadcastFormState::previewBroadcast([
                            'type' => $get('type'),
                            'tf_home_team' => $get('tf_home_team'),
                            'tf_away_team' => $get('tf_away_team'),
                            'tf_kickoff_at' => $get('tf_kickoff_at'),
                            'tf_odds_home' => $get('tf_odds_home'),
                            'tf_odds_draw' => $get('tf_odds_draw'),
                            'tf_odds_away' => $get('tf_odds_away'),
                            'tf_cta' => $get('tf_cta'),
                            'text_en' => $get('text_en'),
                            'text_am' => $get('text_am'),
                            'media_en_upload' => $get('media_en_upload'),
                            'media_am_upload' => $get('media_am_upload'),
                            'buttons_data' => $get('buttons_data') ?? [],
                        ]);

                        $result = app(BroadcastTestSender::class)->send($broadcast);

                        Notification::make()
                            ->title($result['recipients'] === 0
                                ? 'No test recipients configured'
                                : "Test sent to {$result['sent']} of {$result['recipients']} recipients")
                            ->status($result['recipients'] === 0 ? 'warning' : 'success')
                            ->send();
                    }),
            ]),
        ];
    }

    /** @return array<mixed> */
    public static function reviewStep(): array
    {
        return [
            Text::make(fn (Get $get): string => 'Type: '.match ($get('type')) {
                'match_card' => 'Match promo',
                'poll' => 'Poll',
                default => 'Standard',
            }),
            Text::make(function (Get $get): string {
                $filter = BroadcastFormState::audienceFilter([
                    'aud_joined_after' => $get('aud_joined_after'),
                    'aud_joined_before' => $get('aud_joined_before'),
                    'aud_active_last_days' => $get('aud_active_last_days'),
                    'aud_inactive_days' => $get('aud_inactive_days'),
                    'aud_language' => $get('aud_language'),
                    'aud_source' => $get('aud_source'),
                    'aud_in_channel' => $get('aud_in_channel'),
                ]);
                $count = app(AudienceQuery::class)->estimatedCount($filter);
                $summary = app(AudienceQuery::class)->describe($filter);

                $line = "Audience: {$summary} — currently ".number_format($count).' users';

                return $count === 0 ? $line.' ⚠️ This audience is EMPTY.' : $line;
            }),
            Text::make(fn (Get $get): string => 'Timing: '.match ($get('timing_mode')) {
                'scheduled' => 'scheduled for '.($get('scheduled_at') ?? '?').' (Addis time)',
                'recurring' => 'recurring — '.($get('rec_frequency') ?? '?').' at '.($get('rec_time') ?? '?').' (Addis time)',
                default => 'send immediately on confirm',
            }),
            Text::make('Confirming saves the campaign and applies the timing above. Immediate sends start right away.'),
        ];
    }
}
