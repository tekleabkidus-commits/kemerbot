<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Telegram\TelegramWebhookProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * All real update handling happens here on the telegram-interactive queue —
 * the webhook HTTP request only verifies, dedupes and enqueues (spec §9).
 */
class ProcessTelegramUpdate implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 30];

    public function __construct(public readonly array $update) {}

    public function handle(TelegramWebhookProcessor $processor): void
    {
        $processor->process($this->update);
    }
}
