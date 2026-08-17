<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Enums\MediaKind;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramRateLimiter;
use App\Services\Telegram\TelegramResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Shared send abstraction for interactive traffic (spec §10). Every send
 * passes the global rate limiter, honors 429 retry_after, marks 403s as
 * blocked, and persists Telegram file_ids for media reuse (spec §4.13).
 */
final class BotMessageSender
{
    public function __construct(
        private readonly TelegramClient $client,
        private readonly TelegramRateLimiter $limiter,
        private readonly UserService $users,
    ) {}

    public function sendToUser(User $user, ?string $text, ?MediaFile $media = null, ?array $replyMarkup = null): TelegramResponse
    {
        if ($user->blocked_bot) {
            return TelegramResponse::failure(403, 'skipped: user has blocked the bot');
        }

        $this->limiter->acquire();
        $response = $this->dispatchSend($user->tg_chat_id, $text, $media, $replyMarkup);

        if ($response->rateLimited()) {
            $this->limiter->pause($response->retryAfter ?? 3);
            $this->limiter->acquire();
            $response = $this->dispatchSend($user->tg_chat_id, $text, $media, $replyMarkup);
        }

        if ($response->blockedByUser()) {
            $this->users->markBlocked($user);
        }

        if ($response->successful() && $media !== null && $media->tg_file_id === null) {
            $fileId = $response->fileId();

            if ($fileId !== null) {
                $media->update(['tg_file_id' => $fileId]);
            }
        }

        return $response;
    }

    private function dispatchSend(int $chatId, ?string $text, ?MediaFile $media, ?array $replyMarkup): TelegramResponse
    {
        if ($media === null) {
            return $this->client->sendText($chatId, (string) $text, $replyMarkup);
        }

        $payload = $media->tg_file_id ?? Storage::path($media->path);

        return match ($media->kind) {
            MediaKind::Photo => $this->client->sendPhoto($chatId, $payload, $text, $replyMarkup),
            MediaKind::Video => $this->client->sendVideo($chatId, $payload, $text, $replyMarkup),
            MediaKind::Animation => $this->client->sendAnimation($chatId, $payload, $text, $replyMarkup),
        };
    }
}
