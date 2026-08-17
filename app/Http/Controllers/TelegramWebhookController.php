<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessTelegramUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Webhook entry point (spec §9): secret already verified by middleware →
 * parse safely → dedupe by update_id (Redis SETNX) → enqueue → 200 fast.
 * Always 200 after auth so Telegram never retry-storms us.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $update = $request->json()->all();
        $updateId = $update['update_id'] ?? null;

        if (! is_int($updateId)) {
            Log::info('telegram.webhook.malformed', ['keys' => array_keys($update)]);

            return response()->json(['ok' => true]);
        }

        if (! $this->firstTimeSeen($updateId)) {
            return response()->json(['ok' => true]);
        }

        ProcessTelegramUpdate::dispatch($update)
            ->onQueue(config('telegram.queues.interactive'));

        return response()->json(['ok' => true]);
    }

    private function firstTimeSeen(int $updateId): bool
    {
        return (bool) Redis::set(
            'telegram:update:'.$updateId,
            '1',
            'EX',
            (int) config('telegram.update_dedupe_ttl_seconds'),
            'NX',
        );
    }
}
