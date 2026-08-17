<?php

declare(strict_types=1);

namespace App\Services\Telegram;

/**
 * Full in-memory Telegram fake for tests (spec §14: tests never call real
 * Telegram). Records every call, returns realistic payloads, and lets tests
 * queue failures per chat and control membership answers.
 */
final class FakeTelegramClient implements TelegramClient
{
    /** @var list<array{method: string, chat_id: int|string|null, params: array}> */
    public array $calls = [];

    /** @var array<int, list<TelegramResponse>> */
    private array $queuedResponses = [];

    /** @var array<int, string> */
    private array $membership = [];

    private bool $membershipNetworkError = false;

    private int $nextMessageId = 1000;

    private int $nextFileId = 1;

    // ---- test configuration -------------------------------------------------

    /** Queue a canned response for the next send to a chat (FIFO). */
    public function queueResponse(int $chatId, TelegramResponse $response, int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->queuedResponses[$chatId][] = $response;
        }
    }

    /** Convenience: the next send to this chat fails with 403 (user blocked bot). */
    public function blockNextSend(int $chatId): void
    {
        $this->queueResponse($chatId, TelegramResponse::failure(403, 'Forbidden: bot was blocked by the user'));
    }

    public function setMembership(int $userId, string $status): void
    {
        $this->membership[$userId] = $status;
    }

    public function failMembershipChecks(bool $fail = true): void
    {
        $this->membershipNetworkError = $fail;
    }

    // ---- test inspection ----------------------------------------------------

    /** @return list<array{method: string, chat_id: int|string|null, params: array}> */
    public function sentTo(int $chatId): array
    {
        return array_values(array_filter(
            $this->calls,
            fn (array $c) => $c['chat_id'] === $chatId && str_starts_with($c['method'], 'send'),
        ));
    }

    public function lastSentTo(int $chatId): ?array
    {
        $sent = $this->sentTo($chatId);

        return $sent === [] ? null : end($sent);
    }

    /** @return list<array> */
    public function callsTo(string $method): array
    {
        return array_values(array_filter($this->calls, fn (array $c) => $c['method'] === $method));
    }

    public function nothingSent(): bool
    {
        return array_filter($this->calls, fn (array $c) => str_starts_with($c['method'], 'send')) === [];
    }

    // ---- TelegramClient -----------------------------------------------------

    public function sendText(int $chatId, string $text, ?array $replyMarkup = null, ?string $parseMode = null): TelegramResponse
    {
        return $this->recordSend('sendMessage', $chatId, [
            'text' => $text,
            'reply_markup' => $replyMarkup,
            'parse_mode' => $parseMode,
        ]);
    }

    public function sendPhoto(int $chatId, string $photo, ?string $caption = null, ?array $replyMarkup = null, ?string $parseMode = null): TelegramResponse
    {
        return $this->recordSend('sendPhoto', $chatId, [
            'photo' => $photo,
            'caption' => $caption,
            'reply_markup' => $replyMarkup,
        ], mediaField: 'photo');
    }

    public function sendVideo(int $chatId, string $video, ?string $caption = null, ?array $replyMarkup = null, ?string $parseMode = null): TelegramResponse
    {
        return $this->recordSend('sendVideo', $chatId, [
            'video' => $video,
            'caption' => $caption,
            'reply_markup' => $replyMarkup,
        ], mediaField: 'video');
    }

    public function sendAnimation(int $chatId, string $animation, ?string $caption = null, ?array $replyMarkup = null, ?string $parseMode = null): TelegramResponse
    {
        return $this->recordSend('sendAnimation', $chatId, [
            'animation' => $animation,
            'caption' => $caption,
            'reply_markup' => $replyMarkup,
        ], mediaField: 'animation');
    }

    public function sendPoll(int $chatId, string $question, array $options, bool $isAnonymous = true): TelegramResponse
    {
        return $this->recordSend('sendPoll', $chatId, [
            'question' => $question,
            'options' => $options,
            'is_anonymous' => $isAnonymous,
        ], poll: true);
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): TelegramResponse
    {
        $this->calls[] = [
            'method' => 'answerCallbackQuery',
            'chat_id' => null,
            'params' => ['callback_query_id' => $callbackQueryId, 'text' => $text, 'show_alert' => $showAlert],
        ];

        return TelegramResponse::success();
    }

    public function getChatMember(int|string $chatId, int $userId): TelegramResponse
    {
        $this->calls[] = ['method' => 'getChatMember', 'chat_id' => $chatId, 'params' => ['user_id' => $userId]];

        if ($this->membershipNetworkError) {
            return TelegramResponse::networkFailure('fake network error');
        }

        $status = $this->membership[$userId] ?? 'member';

        return TelegramResponse::success(['status' => $status, 'user' => ['id' => $userId]]);
    }

    public function editMessageText(int $chatId, int $messageId, string $text, ?array $replyMarkup = null, ?string $parseMode = null): TelegramResponse
    {
        $this->calls[] = ['method' => 'editMessageText', 'chat_id' => $chatId, 'params' => ['message_id' => $messageId, 'text' => $text, 'reply_markup' => $replyMarkup]];

        return TelegramResponse::success(['message_id' => $messageId]);
    }

    public function editMessageReplyMarkup(int $chatId, int $messageId, ?array $replyMarkup = null): TelegramResponse
    {
        $this->calls[] = ['method' => 'editMessageReplyMarkup', 'chat_id' => $chatId, 'params' => ['message_id' => $messageId, 'reply_markup' => $replyMarkup]];

        return TelegramResponse::success(['message_id' => $messageId]);
    }

    public function setWebhook(string $url, string $secret, array $allowedUpdates): TelegramResponse
    {
        // Never store the secret in the recorded call log.
        $this->calls[] = ['method' => 'setWebhook', 'chat_id' => null, 'params' => ['url' => $url, 'allowed_updates' => $allowedUpdates]];

        return TelegramResponse::success();
    }

    public function getWebhookInfo(): TelegramResponse
    {
        $this->calls[] = ['method' => 'getWebhookInfo', 'chat_id' => null, 'params' => []];

        return TelegramResponse::success(['url' => 'https://fake.test/telegram/webhook']);
    }

    private function recordSend(string $method, int $chatId, array $params, ?string $mediaField = null, bool $poll = false): TelegramResponse
    {
        $this->calls[] = ['method' => $method, 'chat_id' => $chatId, 'params' => $params];

        if (! empty($this->queuedResponses[$chatId])) {
            return array_shift($this->queuedResponses[$chatId]);
        }

        $result = ['message_id' => $this->nextMessageId++, 'chat' => ['id' => $chatId]];

        if ($mediaField === 'photo') {
            $result['photo'] = [['file_id' => 'fake-file-'.$this->nextFileId++]];
        } elseif ($mediaField !== null) {
            $result[$mediaField] = ['file_id' => 'fake-file-'.$this->nextFileId++];
        }

        if ($poll) {
            $result['poll'] = ['id' => 'fake-poll-'.$this->nextMessageId, 'question' => $params['question']];
        }

        return TelegramResponse::success($result);
    }
}
