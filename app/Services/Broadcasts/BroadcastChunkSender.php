<?php

declare(strict_types=1);

namespace App\Services\Broadcasts;

use App\Enums\BroadcastStatus;
use App\Enums\BroadcastType;
use App\Enums\FailureCategory;
use App\Enums\MediaKind;
use App\Models\Broadcast;
use App\Models\BroadcastFailure;
use App\Models\PollInstance;
use App\Models\User;
use App\Services\Bot\BotLocaleResolver;
use App\Services\Bot\UserService;
use App\Services\ContactPolicy;
use App\Services\MediaPayload;
use App\Services\MediaUnavailable;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramRateLimiter;
use App\Services\Telegram\TelegramResponse;
use Carbon\Carbon;

/** Sends leased recipients and commits each outcome with its counter. */
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
     * @param  list<array{id: string, user_id: int, chat_id: int, attempt: int}>  $entries
     */
    public function sendChunk(Broadcast $broadcast, array $entries): void
    {
        $usersById = User::query()
            ->whereIn('id', array_column($entries, 'user_id'))
            ->get()
            ->keyBy('id');

        foreach ($entries as $entry) {
            $broadcast->refresh();
            if ($broadcast->status !== BroadcastStatus::Sending) {
                $this->snapshot->defer($broadcast->id, $entry, 1);

                continue;
            }
            if (($broadcast->expires_at && $broadcast->expires_at->isPast()) ||
                ($broadcast->type === BroadcastType::MatchCard && filled($broadcast->template_fields['kickoff_at'] ?? null) && Carbon::parse($broadcast->template_fields['kickoff_at'])->isPast())) {
                $this->snapshot->finish($broadcast->id, $entry, 'skipped');

                continue;
            }
            if ($this->snapshot->isDone($broadcast->id, $entry['user_id'])) {
                continue;
            }
            $user = $usersById->get($entry['user_id']);
            $decision = $user ? app(ContactPolicy::class)->reserve($user, 'broadcast:'.$broadcast->id.':'.$user->id, $broadcast->topic ?? 'general') : 'skip';
            if ($decision === 'wait') {
                $this->snapshot->defer($broadcast->id, $entry);

                continue;
            }
            if ($decision === 'skip') {
                $this->snapshot->finish($broadcast->id, $entry, $user?->blocked_bot ? 'blocked' : 'skipped');

                continue;
            }
            $broadcast->setAttribute('delivery_variant', $entry['variant'] ?? 'a');
            try {
                $outcome = $this->sendToUser($broadcast, $user, $entry['attempt']);
            } catch (MediaUnavailable|InvalidBroadcastTransition $e) {
                $this->recordFailure($broadcast, $user, TelegramResponse::failure(0, 'Content or media is unavailable'), FailureCategory::Other, $entry['attempt']);
                $outcome = 'failed';
            }
            if ($outcome === 'requeued') {
                $this->snapshot->requeueForRetry($broadcast->id, $entry);

                continue;
            }
            $this->snapshot->finish($broadcast->id, $entry, $outcome);
        }
    }

    /** @return 'sent'|'blocked'|'failed'|'requeued' */
    private function sendToUser(Broadcast $broadcast, User $user, int $attempt): string
    {
        if ($broadcast->type === BroadcastType::Poll) {
            return $this->sendPollTo($broadcast, $user, $attempt);
        }

        $message = $this->renderer->render($broadcast, $user);
        $response = $this->attemptSend($user, $message);

        if ($response->successful()) {
            $this->persistMediaFileId($message, $response);

            return 'sent';
        }

        return $this->classifyFailure($broadcast, $user, $response, $attempt);
    }

    /**
     * Native poll sends (spec §5.8): localized question/options; the returned
     * Telegram poll id is correlated via poll_instances (documented exception).
     *
     * @return 'sent'|'blocked'|'failed'|'requeued'
     */
    private function sendPollTo(Broadcast $broadcast, User $user, int $attempt): string
    {
        $poll = $broadcast->poll;

        if ($poll === null) {
            $this->recordFailure($broadcast, $user, TelegramResponse::failure(0, 'poll broadcast has no poll definition'), FailureCategory::Other, $attempt);

            return 'failed';
        }

        $lang = $this->locale->resolve($user);
        $question = $this->locale->pickFromMap($poll->question, $lang) ?? '?';
        $options = $poll->options[$lang->value] ?? $poll->options['en'] ?? [];

        $this->limiter->acquire(chatId: $user->tg_chat_id);
        $response = $this->client->sendPoll($user->tg_chat_id, $question, $options, $poll->is_anonymous);

        if ($response->rateLimited()) {
            $this->limiter->pause($response->retryAfter ?? 3);
            $this->limiter->acquire(chatId: $user->tg_chat_id);
            $response = $this->client->sendPoll($user->tg_chat_id, $question, $options, $poll->is_anonymous);
        }

        if ($response->successful()) {
            $tgPollId = is_array($response->result) ? ($response->result['poll']['id'] ?? null) : null;

            if ($tgPollId !== null) {
                // firstOrCreate: an at-least-once redelivery must not violate
                // the tg_poll_id unique constraint.
                PollInstance::query()->firstOrCreate(
                    ['tg_poll_id' => (string) $tgPollId],
                    ['poll_id' => $poll->id, 'user_id' => $user->id, 'sent_at' => now()],
                );
            }

            return 'sent';
        }

        return $this->classifyFailure($broadcast, $user, $response, $attempt);
    }

    /** @return 'blocked'|'failed'|'requeued' */
    private function classifyFailure(Broadcast $broadcast, User $user, TelegramResponse $response, int $attempt): string
    {
        if ($response->blockedByUser()) {
            $this->users->markBlocked($user);
            $this->recordFailure($broadcast, $user, $response, FailureCategory::Blocked, $attempt);

            return 'blocked';
        }

        if ($response->retryable()) {
            if ($attempt < (int) config('telegram.send_max_attempts')) {
                return 'requeued';
            }

            $this->recordFailure($broadcast, $user, $response, FailureCategory::Network, $attempt);

            return 'failed';
        }

        $category = $response->errorCode === 400 ? FailureCategory::Invalid : FailureCategory::Other;
        $this->recordFailure($broadcast, $user, $response, $category, $attempt);

        return 'failed';
    }

    private function attemptSend(User $user, RenderedMessage $message): TelegramResponse
    {
        $this->limiter->acquire(chatId: $user->tg_chat_id);
        $response = $this->dispatchSend($user->tg_chat_id, $message);

        // 429: pause the global bucket for retry_after, then one immediate retry.
        if ($response->rateLimited()) {
            $this->limiter->pause($response->retryAfter ?? 3);
            $this->limiter->acquire(chatId: $user->tg_chat_id);
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

        return app(MediaPayload::class)->withFile($media, fn (string $payload) => match ($media->kind) {
            MediaKind::Photo => $this->client->sendPhoto($chatId, $payload, $message->text, $message->replyMarkup),
            MediaKind::Video => $this->client->sendVideo($chatId, $payload, $message->text, $message->replyMarkup),
            MediaKind::Animation => $this->client->sendAnimation($chatId, $payload, $message->text, $message->replyMarkup),
        });
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

    private function recordFailure(Broadcast $broadcast, User $user, TelegramResponse $response, FailureCategory $category, int $attempt): void
    {
        $now = now();

        BroadcastFailure::query()->upsert(
            [[
                'broadcast_id' => $broadcast->id,
                'user_id' => $user->id,
                'tg_error_code' => $response->errorCode,
                'category' => $category->value,
                'sanitized_error' => mb_substr((string) $response->description, 0, 500),
                'attempts' => $attempt,
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
