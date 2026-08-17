<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MessageDirection;
use App\Models\TelegramMessage;
use App\Models\User;
use App\Services\Bot\BotMessageSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Admin 1:1 message (spec §5.7): shared sender abstraction on the interactive
 * queue, recorded in telegram_messages. A light tool — not a support desk.
 */
class SendDirectMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 30];

    public function __construct(
        public readonly int $userId,
        public readonly int $adminId,
        public readonly string $text,
    ) {}

    public function handle(BotMessageSender $sender): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $response = $sender->sendToUser($user, $this->text);

        if (! $response->successful()) {
            Log::info('direct_message.not_delivered', [
                'user_id' => $user->id,
                'error_code' => $response->errorCode,
            ]);

            return;
        }

        TelegramMessage::query()->create([
            'user_id' => $user->id,
            'tg_message_id' => $response->messageId(),
            'direction' => MessageDirection::AdminOutbound,
            'type' => 'text',
            'text' => $this->text,
            'admin_id' => $this->adminId,
            'sent_at' => now(),
        ]);
    }
}
