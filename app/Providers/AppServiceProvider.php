<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Automation;
use App\Models\Broadcast;
use App\Models\KeywordReply;
use App\Models\MenuItem;
use App\Models\Poll;
use App\Models\TrackingLink;
use App\Observers\AuditsAdminMutations;
use App\Services\SettingsService;
use App\Services\Telegram\HttpTelegramClient;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramRateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
        $this->app->singleton(TelegramRateLimiter::class);

        $this->app->singleton(TelegramClient::class, function () {
            return new HttpTelegramClient(
                token: (string) config('telegram.bot_token'),
                baseUrl: (string) config('telegram.api_base_url'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every admin-context create/edit/delete on managed content is audited
        // (spec §5.9). Bot/queue traffic has no acting admin and is skipped.
        foreach ([
            MenuItem::class,
            KeywordReply::class,
            Broadcast::class,
            Automation::class,
            TrackingLink::class,
            Poll::class,
            Admin::class,
        ] as $model) {
            $model::observe(AuditsAdminMutations::class);
        }
    }
}
