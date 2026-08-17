<?php

namespace App\Providers;

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
        //
    }
}
