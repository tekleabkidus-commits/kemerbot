<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessTelegramUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $id = $payload['update_id'] ?? null;
        if (! is_int($id)) {
            return response()->json(['ok' => true]);
        }
        $created = DB::table('telegram_updates')->insertOrIgnore(['update_id' => $id, 'payload' => json_encode($payload), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        if ($created) {
            try {
                ProcessTelegramUpdate::dispatch($payload)->onQueue(config('telegram.queues.interactive'));
            } catch (\Throwable $e) {
                Log::warning('telegram.inbox.dispatch_deferred', ['update_id' => $id]);
            }
        }
        try {
            Redis::set('telegram:last_webhook_ok_at', now()->toIso8601String());
        } catch (\Throwable $e) {
        }

        return response()->json(['ok' => true]);
    }
}
