<?php

declare(strict_types=1);

namespace App\Services\Broadcasts;

use App\Enums\BroadcastType;
use App\Enums\FailureCategory;
use App\Enums\MediaKind;
use App\Models\Broadcast;
use App\Models\BroadcastFailure;
use App\Models\PollInstance;
use App\Models\User;
use App\Services\Bot\BotLocaleResolver;
use App\Services\Bot\UserService;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramRateLimiter;
use App\Services\Telegram\TelegramResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Sends one chunk of a broadcast (spec §7): render per user, send through the
 * global rate limiter, classify results, ONE atomic counter UPDATE per chunk
 * (spec §4.14). Retryable failures are re-queued to the snapshot tail with an
 * attempt count instead of sleeping in-process — the backoff is positional.
 */
final class BroadcastChunkSender
{
    public function __construct(
        private readonly AudienceSnapshot $snapshot,
        private readonly BroadcastRenderer $renderer,
        private readonly TelegramClient $client,
        private readonly TelegramRateLimiter $limiter,
        private readonly UserService $users,
        private readonly BotLocaleResolver $locale,
    ) {}

    /**
     * @param  list<array{0: int, 1: int}>  $chunk  [userId, tgChatId] pairs
     */
    public function sendChunk(Broadcast $broadcast, array $chunk): void
    {
        $userIds = array_column($chunk, 0);
        $usersById = User::query()->whereIn('id', $userIds)->get()->keyBy('id');

        $sent = 0;
        $blocked = 0;
        $failed = 0;

        foreach ($chunk as [$userId, $chatId]) {
            $user = $usersById->get($userId);

            if ($user === null || $user->blocked_bot) {
                // Deleted or blocked since the snapshot froze: count as blocked-skip.
                $blocked++;

                continue;
            }

            $outcome = $this->sendToUser($broadcast, $user);

            match ($outcome) {
                'sent' => $sent++,
                'blocked' => $blocked++,
                'failed' => $failed++,
                'requeued' => null,
            };
        }

        // Decision 14: one atomic counter UPDATE per chunk, never per message.
        if ($sent > 0 || $blocked > 0 || $failed > 0) {
            Broadcast::query()->whereKey($broadcast->id)->update([
                'sent' => DB::raw('sent + '.$sent),
                'blocked' => DB::raw('blocked + '.$blocked),
                'failed' => DB::raw('failed + '.$failed),
            ]);
        }
    }

    /** @return 'sent'|'blocked'|'failed'|'requeued' */
    private function sendToUser(Broadcast $broadcast, User $user): string
    {
        if ($broadcast->type === BroadcastType::Poll) {
            return $this->sendPollTo($broadcast, $user);
        }

        $message = $this->renderer->render($broadcast, $user);
        $response = $this->attemptSend($broadcast, $user, $message);

        if ($response->successful()) {
            $this->persistMediaFileId($message, $response);

            return 'sent';
        }

        return $this->classifyFailure($broadcast, $user, $response);
    }

    /**
     * Native poll sends (spec §5.8): localized question/options; the returned
     * Telegram poll id is correlated via poll_instances (documented exception).
     *
     * @return 'sent'|'blocked'|'failed'|'requeued'
     */
    private function sendPollTo(Broadcast $broadcast, User $user): string
    {
        $poll = $broadcast->poll;

        if ($poll === null) {
            $this->recordFailure($broadcast, $user, TelegramResponse::failure(0, 'poll broadcast has no poll definition'), FailureCategory::Other);

            return 'failed';
        }

        $lang = $this->locale->resolve($user);
        $question = $this->locale->pickFromMap($poll->question, $lang) ?? '?';
        $options = $poll->options[$lang->value] ?? $poll->options['en'] ?? [];

        $this->limiter->acquire();
        $response = $this->client->sendPoll($user->tg_chat_id, $question, $options, $poll->is_anonymous);

        if ($response->rateLimited()) {
            $this->limiter->pause($response->retryAfter ?? 3);
            $this->limiter->acquire();
            $response = $this->client->sendPoll($user->tg_chat_id, $question, $options, $poll->is_anonymous);
        }

        if ($response->successful()) {
            $tgPollId = is_array($response->result) ? ($response->result['poll']['id'] ?? null) : null;

            if ($tgPollId !== null) {
                PollInstance::query()->create([
                    'poll_id' => $poll->id,
                    'user_id' => $user->id,
                    'tg_poll_id' => (string) $tgPollId,
                    'sent_at' => now(),
                ]);
            }

            return 'sent';
        }

        return $this->classifyFailure($broadcast, $user, $response);
    }

    /** @return 'blocked'|'failed'|'requeued' */
    private function classifyFailure(Broadcast $broadcast, User $user, TelegramResponse $response): string
    {
        if ($response->blockedByUser()) {
            $this->users->markBlocked($user);
            $this->recordFailure($broadcast, $user, $response, FailureCategory::Blocked);

            return 'blocked';
        }

        if ($response->retryable()) {
            if ($this->snapshot->attemptsFor($broadcast->id, $user->id) < (int) config('telegram.send_max_attempts')) {
                $this->snapshot->requeueForRetry($broadcast->id, $user->id, $user->tg_chat_id);

                return 'requeued';
            }

            $this->recordFailure($broadcast, $user, $response, FailureCategory::Network);

            return 'failed';
        }

        $category = $response->errorCode === 400 ? FailureCategory::Invalid : FailureCategory::Other;
        $this->recordFailure($broadcast, $user, $response, $category);

        return 'failed';
    }

    private function attemptSend(Broadcast $broadcast, User $user, RenderedMessage $message): TelegramResponse
    {
        $this->limiter->acquire();
        $response = $this->dispatchSend($user->tg_chat_id, $message);

        // 429: pause the global bucket for retry_after, then one immediate retry.
        if ($response->rateLimited()) {
            $this->limiter->pause($response->retryAfter ?? 3);
            $this->limiter->acquire();
            $response = $this->dispatchSend($user->tg_chat_id, $message);
        }

        return $response;
    }

    private function dispatchSend(int $chatId, RenderedMessage $message): TelegramResponse
    {
        $media = $message->media;

        if ($media === null) {
            return $this->client->sendText($chatId, (string) $message->text, $message->replyMarkup);
        }

        $payload = $media->tg_file_id ?? Storage::path($media->path);

        return match ($media->kind) {
            MediaKind::Photo => $this->client->sendPhoto($chatId, $payload, $message->text, $message->replyMarkup),
            MediaKind::Video => $this->client->sendVideo($chatId, $payload, $message->text, $message->replyMarkup),
            MediaKind::Animation => $this->client->sendAnimation($chatId, $payload, $message->text, $message->replyMarkup),
        };
    }

    /** First successful send persists the Telegram file_id for reuse (spec §4.13). */
    private function persistMediaFileId(RenderedMessage $message, TelegramResponse $response): void
    {
        if ($message->media !== null && $message->media->tg_file_id === null) {
            $fileId = $response->fileId();

            if ($fileId !== null) {
                $message->media->update(['tg_file_id' => $fileId]);
            }
        }
    }

    private function recordFailure(Broadcast $broadcast, User $user, TelegramResponse $response, FailureCategory $category): void
    {
        $now = now();

        BroadcastFailure::query()->upsert(
            [[
                'broadcast_id' => $broadcast->id,
                'user_id' => $user->id,
                'tg_error_code' => $response->errorCode,
                'category' => $category->value,
                'sanitized_error' => mb_substr((string) $response->description, 0, 500),
                'attempts' => $this->snapshot->attemptsFor($broadcast->id, $user->id) + 1,
                'first_failed_at' => $now,
                'last_failed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            uniqueBy: ['broadcast_id', 'user_id'],
            update: ['tg_error_code', 'category', 'sanitized_error', 'attempts', 'last_failed_at', 'updated_at'],
        );
    }
}
