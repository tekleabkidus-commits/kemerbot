<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Telegram\TelegramWebhookProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * All real update handling happens here on the telegram-interactive queue —
 * the webhook HTTP request only verifies, dedupes and enqueues (spec §9).
 */
class ProcessTelegramUpdate implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 30];

    public function __construct(public readonly array $update) {}

    public function handle(TelegramWebhookProcessor $processor): void
    {
        $id = $this->update['update_id'] ?? null;
        if ($id === null) {
            $processor->process($this->update);

            return;
        }
        $created = DB::table('telegram_updates')->insertOrIgnore(['update_id' => $id, 'payload' => json_encode($this->update), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $claimed = DB::table('telegram_updates')->where('update_id', $id)->where('status', 'pending')
            ->update(['status' => 'processing', 'claimed_at' => now(), 'attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]);
        if (! $claimed) {
            return;
        }
        try {
            $processor->process($this->update);
            DB::table('telegram_updates')->where('update_id', $id)->update(['status' => 'processed', 'processed_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable $e) {
            DB::table('telegram_updates')->where('update_id', $id)->update(['status' => 'pending', 'updated_at' => now()]);
            throw $e;
        }
    }
}
