<?php

namespace App\Filament\Pages;

use App\Filament\Support\FormMedia;
use App\Models\MediaFile;
use App\Models\Setting;
use App\Policies\SettingPolicy;
use App\Services\AuditLogger;
use App\Services\MediaFileService;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * DB-backed runtime settings (spec §5.9). Secrets never appear here — the
 * bot token/webhook secret live in env only; the page shows a connection
 * status line instead (spec §4.7). Marketers may edit content settings;
 * infrastructure keys are Owner-only, enforced server-side in save().
 */
class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 3;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = app(SettingsService::class);
        $welcome = (array) $settings->get('welcome.message', []);
        $features = (array) $settings->get('features', []);

        $mediaId = $settings->get('welcome.media_file_id');
        $mediaPath = $mediaId !== null ? MediaFile::query()->find($mediaId)?->path : null;

        $this->form->fill([
            'channel_id' => $settings->get('channel.id'),
            'channel_url' => $settings->get('channel.url'),
            'default_language' => $settings->get('bot.default_language', 'en'),
            'send_rate' => $settings->get('telegram.send_rate', (int) config('telegram.send_rate')),
            'test_recipients' => array_map('strval', (array) $settings->get('broadcast.test_recipient_chat_ids', [])),
            'webapp_url' => $settings->get('webapp.url'),
            'welcome_en' => $welcome['en'] ?? null,
            'welcome_am' => $welcome['am'] ?? null,
            'welcome_media_upload' => $mediaPath,
            'feature_referrals' => (bool) ($features['referrals'] ?? true),
            'feature_polls' => (bool) ($features['polls'] ?? true),
            'feature_mini_app' => (bool) ($features['mini_app'] ?? true),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $isOwner = auth()->user()->isOwner();
        $isViewer = auth()->user()->isViewer();

        return $schema
            ->components([
                Text::make(fn (): string => filled(config('telegram.bot_token'))
                    ? 'Telegram Bot: Connected ✓'
                    : 'Telegram Bot: NOT configured — set TELEGRAM_BOT_TOKEN in the environment.')
                    ->weight('bold'),
                Section::make('Welcome message')
                    ->description('The reply every user gets on /start. Personalization: {first_name}.')
                    ->schema([
                        Textarea::make('welcome_en')
                            ->label('Welcome (EN)')
                            ->rows(4)
                            ->required()
                            ->maxLength(4000)
                            ->disabled($isViewer),
                        Textarea::make('welcome_am')
                            ->label('Welcome (AM)')
                            ->rows(4)
                            ->maxLength(4000)
                            ->helperText('Optional — Amharic users fall back to English if empty.')
                            ->disabled($isViewer),
                        FileUpload::make('welcome_media_upload')
                            ->label('Welcome media (optional)')
                            ->disk(config('filesystems.default'))
                            ->directory('media')
                            ->acceptedFileTypes(MediaFileService::acceptedMimeTypes())
                            ->maxSize(50 * 1024)
                            ->disabled($isViewer),
                        Select::make('default_language')
                            ->label('Default bot language')
                            ->options(['en' => 'English', 'am' => 'Amharic'])
                            ->required()
                            ->native(false)
                            ->disabled($isViewer),
                    ]),
                Section::make('Infrastructure')
                    ->description($isOwner ? 'Owner-only settings.' : 'Only Owners can change these settings.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('channel_id')
                            ->label('Force-join channel ID')
                            ->placeholder('-100xxxxxxxxxx')
                            ->helperText('Leave empty to disable the join gate. The bot must be an admin of this channel.')
                            ->disabled(! $isOwner),
                        TextInput::make('channel_url')
                            ->label('Channel URL')
                            ->url()
                            ->placeholder('https://t.me/...')
                            ->disabled(! $isOwner),
                        TextInput::make('send_rate')
                            ->label('Send rate (messages/second)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue((int) config('telegram.send_rate'))
                            ->helperText('Hard ceiling from env: '.(int) config('telegram.send_rate').' msg/s.')
                            ->disabled(! $isOwner),
                        TextInput::make('webapp_url')
                            ->label('Web App (Mini App) URL')
                            ->url()
                            ->disabled(! $isOwner),
                        TagsInput::make('test_recipients')
                            ->label('Broadcast test recipient chat IDs')
                            ->placeholder('Add a Telegram chat ID')
                            ->disabled(! $isOwner),
                        Toggle::make('feature_referrals')->label('Referrals enabled')->disabled(! $isOwner)->inline(false),
                        Toggle::make('feature_polls')->label('Polls enabled')->disabled(! $isOwner)->inline(false),
                        Toggle::make('feature_mini_app')->label('Mini App button enabled')->disabled(! $isOwner)->inline(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('update', Setting::class), 403);

        $state = $this->form->getState();
        $settings = app(SettingsService::class);
        $policy = new SettingPolicy;
        $admin = auth()->user();

        $mediaId = null;

        if (filled($state['welcome_media_upload'] ?? null)) {
            $current = $settings->get('welcome.media_file_id');
            $currentMedia = $current !== null ? MediaFile::query()->find($current) : null;
            $mediaId = FormMedia::resolveMediaFileId($state['welcome_media_upload'], $currentMedia);
        }

        $values = [
            'channel.id' => filled($state['channel_id'] ?? null) ? $state['channel_id'] : null,
            'channel.url' => $state['channel_url'] ?? null,
            'bot.default_language' => $state['default_language'],
            'telegram.send_rate' => (int) $state['send_rate'],
            'broadcast.test_recipient_chat_ids' => array_values(array_filter(array_map('intval', $state['test_recipients'] ?? []))),
            'webapp.url' => $state['webapp_url'] ?? null,
            'welcome.message' => ['en' => $state['welcome_en'], 'am' => $state['welcome_am']],
            'welcome.media_file_id' => $mediaId,
            'features' => [
                'referrals' => (bool) $state['feature_referrals'],
                'polls' => (bool) $state['feature_polls'],
                'mini_app' => (bool) $state['feature_mini_app'],
            ],
        ];

        $changedKeys = [];

        foreach ($values as $key => $value) {
            // Server-side per-key enforcement: silently skip keys this role
            // may not touch (their fields were disabled in the UI anyway).
            if (! $policy->updateKey($admin, $key)) {
                continue;
            }

            if ($settings->get($key) !== $value) {
                $settings->set($key, $value);
                $changedKeys[] = $key;
            }
        }

        if ($changedKeys !== []) {
            app(AuditLogger::class)->log('settings.updated', null, ['keys' => $changedKeys]);
        }

        Notification::make()->title('Settings saved')->success()->send();
    }

    public function getTitle(): string
    {
        return 'Settings';
    }
}
