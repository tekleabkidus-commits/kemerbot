<?php

namespace App\Services;

use App\Jobs\SendDirectMessageJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class DirectMessages
{
    public function queue(int $userId, int $adminId, string $text): string
    {
        $id = (string) Str::uuid();
        DB::table('direct_message_deliveries')->insert(['id' => $id, 'user_id' => $userId, 'admin_id' => $adminId, 'text' => $text, 'status' => 'pending', 'available_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        try {
            SendDirectMessageJob::dispatch($userId, $adminId, $text, $id)->onQueue(config('telegram.queues.interactive'));
        } catch (\Throwable $e) {
            Log::warning('direct_message.dispatch_deferred', ['delivery_id' => $id]);
        }

        return $id;
    }
}
