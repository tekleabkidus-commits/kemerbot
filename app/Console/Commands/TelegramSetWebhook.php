<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Telegram\TelegramClient;
use Illuminate\Console\Command;

/**
 * Registers the webhook with Telegram (spec §17). Usage:
 *   php artisan telegram:set-webhook                  # uses APP_URL
 *   php artisan telegram:set-webhook https://tunnel.example  # dev tunnel
 */
class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {base_url? : Public base URL (defaults to APP_URL)}';

    protected $description = 'Register the Telegram webhook (secret + allowed_updates) and show getWebhookInfo';

    public function handle(TelegramClient $client): int
    {
        $secret = (string) config('telegram.webhook_secret');

        if ($secret === '' || (string) config('telegram.bot_token') === '') {
            $this->error('TELEGRAM_BOT_TOKEN and TELEGRAM_WEBHOOK_SECRET must be set in the environment.');

            return self::FAILURE;
        }

        $base = rtrim((string) ($this->argument('base_url') ?? config('app.url')), '/');
        $url = $base.'/telegram/webhook';

        $response = $client->setWebhook($url, $secret, config('telegram.allowed_updates'));

        if (! $response->successful()) {
            $this->error("setWebhook failed: {$response->description}");

            return self::FAILURE;
        }

        $this->info("Webhook registered: {$url}");

        $info = $client->getWebhookInfo();

        if ($info->successful() && is_array($info->result)) {
            foreach (['url', 'pending_update_count', 'last_error_message'] as $key) {
                if (isset($info->result[$key])) {
                    $this->line("  {$key}: {$info->result[$key]}");
                }
            }
        }

        return self::SUCCESS;
    }
}
