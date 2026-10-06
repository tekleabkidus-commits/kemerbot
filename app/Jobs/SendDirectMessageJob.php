<?php

namespace App\Jobs;

use App\Enums\MessageDirection;
use App\Models\Admin;
use App\Models\TelegramMessage;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Bot\BotMessageSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SendDirectMessageJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    public array $backoff = [5, 30];

    public readonly string $deliveryId;

    public function __construct(public readonly int $userId, public readonly int $adminId, public readonly string $text, ?string $deliveryId = null)
    {
        $this->deliveryId = $deliveryId ?? (string) Str::uuid();
    }

    public function handle(BotMessageSender $sender): void
    {
        $user = User::find($this->userId);
        if (! $user || $user->blocked_bot) {
            DB::table('direct_message_deliveries')->where('id', $this->deliveryId)->where('status', 'pending')->update(['status' => 'failed', 'last_error' => 'Person unavailable or blocked the bot', 'updated_at' => now()]);

            return;
        }
        $admin = Admin::find($this->adminId);
        if (! $admin || ! $admin->can('message', $user)) {
            DB::table('direct_message_deliveries')->where('id', $this->deliveryId)->where('status', 'pending')->update(['status' => 'failed', 'last_error' => 'The sending administrator no longer has permission', 'updated_at' => now()]);

            return;
        }
        DB::table('direct_message_deliveries')->insertOrIgnore(['id' => $this->deliveryId, 'user_id' => $user->id, 'admin_id' => $this->adminId ?: null, 'text' => $this->text, 'status' => 'pending', 'available_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $claimed = DB::table('direct_message_deliveries')->where('id', $this->deliveryId)->where('status', 'pending')->where('available_at', '<=', now())->update(['status' => 'sending', 'attempts' => DB::raw('attempts+1'), 'claimed_at' => now(), 'updated_at' => now()]);
        if (! $claimed) {
            return;
        }
        $attempt = (int) DB::table('direct_message_deliveries')->where('id', $this->deliveryId)->value('attempts');
        $response = $sender->sendToUser($user, $this->text);
        if ($response->retryable() && $attempt < $this->tries) {
            $delay = max($response->retryAfter ?? 0, $this->backoff[min($attempt - 1, count($this->backoff) - 1)]);
            DB::table('direct_message_deliveries')->where('id', $this->deliveryId)->update(['status' => 'pending', 'available_at' => now()->addSeconds($delay), 'last_error' => 'Retryable delivery error', 'updated_at' => now()]);
            $this->release($delay);

            return;
        }
        if (! $response->successful()) {
            DB::table('direct_message_deliveries')->where('id', $this->deliveryId)->update(['status' => 'failed', 'last_error' => mb_substr((string) $response->description, 0, 400), 'updated_at' => now()]);

            return;
        }
        DB::transaction(function () use ($user, $response) {
            DB::table('direct_message_deliveries')->where('id', $this->deliveryId)->update(['status' => 'sent', 'last_error' => null, 'updated_at' => now()]);
            $actor = Admin::query()->sharedLock()->find($this->adminId);
            app(AuditLogger::class)->log('direct_message.delivered', $user, [], $actor);
            TelegramMessage::create(['user_id' => $user->id, 'tg_message_id' => $response->messageId(), 'direction' => MessageDirection::AdminOutbound, 'type' => 'text', 'text' => $this->text, 'admin_id' => $actor?->id, 'sent_at' => now()]);
        });
    }
}
