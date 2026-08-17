<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Production Telegram Bot API client. The bot token appears only in the
 * request URL and is scrubbed from every log line and error message.
 */
final class HttpTelegramClient implements TelegramClient
{
    private const TIMEOUT_SECONDS = 15;

    public function __construct(
        private readonly string $token,
        private readonly string $baseUrl,
    ) {}

    public function sendText(int $chatId, string $text, ?array $replyMarkup = null, ?string $parseMode = null): TelegramResponse
    {
        return $this->call('sendMessage', array_filter([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $replyMarkup,
            'parse_mode' => $parseMode,
        ], fn ($v) => $v !== null));
    }

    public function sendPhoto(int $chatId, string $photo, ?string $caption = null, ?array $replyMarkup = null, ?string $parseMode = null): TelegramResponse
    {
        return $this->sendMedia('sendPhoto', 'photo', $chatId, $photo, $caption, $replyMarkup, $parseMode);
    }

    public function sendVideo(int $chatId, string $video, ?string $caption = null, ?array $replyMarkup = null, ?string $parseMode = null): TelegramResponse
    {
        return $this->sendMedia('sendVideo', 'video', $chatId, $video, $caption, $replyMarkup, $parseMode);
    }

    public function sendAnimation(int $chatId, string $animation, ?string $caption = null, ?array $replyMarkup = null, ?string $parseMode = null): TelegramResponse
    {
        return $this->sendMedia('sendAnimation', 'animation', $chatId, $animation, $caption, $replyMarkup, $parseMode);
    }

    public function sendPoll(int $chatId, string $question, array $options, bool $isAnonymous = true): TelegramResponse
    {
        return $this->call('sendPoll', [
            'chat_id' => $chatId,
            'question' => $question,
            'options' => array_map(fn (string $o) => ['text' => $o], array_values($options)),
            'is_anonymous' => $isAnonymous,
        ]);
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): TelegramResponse
    {
        return $this->call('answerCallbackQuery', array_filter([
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert ?: null,
        ], fn ($v) => $v !== null));
    }

    public function getChatMember(int|string $chatId, int $userId): TelegramResponse
    {
        return $this->call('getChatMember', ['chat_id' => $chatId, 'user_id' => $userId]);
    }

    public function editMessageText(int $chatId, int $messageId, string $text, ?array $replyMarkup = null, ?string $parseMode = null): TelegramResponse
    {
        return $this->call('editMessageText', array_filter([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'reply_markup' => $replyMarkup,
            'parse_mode' => $parseMode,
        ], fn ($v) => $v !== null));
    }

    public function editMessageReplyMarkup(int $chatId, int $messageId, ?array $replyMarkup = null): TelegramResponse
    {
        return $this->call('editMessageReplyMarkup', array_filter([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => $replyMarkup,
        ], fn ($v) => $v !== null));
    }

    public function setWebhook(string $url, string $secret, array $allowedUpdates): TelegramResponse
    {
        return $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => $allowedUpdates,
        ]);
    }

    public function getWebhookInfo(): TelegramResponse
    {
        return $this->call('getWebhookInfo');
    }

    private function sendMedia(string $method, string $field, int $chatId, string $media, ?string $caption, ?array $replyMarkup, ?string $parseMode): TelegramResponse
    {
        $params = array_filter([
            'chat_id' => $chatId,
            'caption' => $caption,
            'reply_markup' => $replyMarkup,
            'parse_mode' => $parseMode,
        ], fn ($v) => $v !== null);

        // Local file → multipart upload; file_id / URL → plain parameter.
        if (is_file($media)) {
            return $this->call($method, $params, uploadField: $field, uploadPath: $media);
        }

        $params[$field] = $media;

        return $this->call($method, $params);
    }

    private function call(string $method, array $params = [], ?string $uploadField = null, ?string $uploadPath = null): TelegramResponse
    {
        $url = "{$this->baseUrl}/bot{$this->token}/{$method}";

        try {
            $pending = Http::timeout(self::TIMEOUT_SECONDS);

            if ($uploadField !== null && $uploadPath !== null) {
                // Multipart: JSON-ish params must be encoded as strings.
                $multipartParams = array_map(
                    fn ($v) => is_array($v) ? json_encode($v) : $v,
                    $params,
                );
                $response = $pending
                    ->attach($uploadField, fopen($uploadPath, 'r'), basename($uploadPath))
                    ->post($url, $multipartParams);
            } else {
                $response = $pending->post($url, $params);
            }
        } catch (ConnectionException $e) {
            $description = $this->scrub($e->getMessage());
            Log::warning('telegram.api.network_error', ['method' => $method, 'error' => $description]);

            return TelegramResponse::networkFailure($description);
        }

        $json = $response->json();

        if (! is_array($json)) {
            $description = "HTTP {$response->status()} with non-JSON body";
            Log::warning('telegram.api.bad_response', ['method' => $method, 'error' => $description]);

            return $response->status() >= 500
                ? TelegramResponse::networkFailure($description)
                : TelegramResponse::failure($response->status(), $description);
        }

        if (($json['ok'] ?? false) === true) {
            return TelegramResponse::success($json['result'] ?? true);
        }

        $errorCode = (int) ($json['error_code'] ?? $response->status());
        $description = $this->scrub((string) ($json['description'] ?? 'unknown Telegram error'));
        $retryAfter = $json['parameters']['retry_after'] ?? null;

        // 403 (blocked) is business-as-usual at scale; don't spam warnings for it.
        if ($errorCode !== 403) {
            Log::warning('telegram.api.error', [
                'method' => $method,
                'error_code' => $errorCode,
                'description' => $description,
                'retry_after' => $retryAfter,
            ]);
        }

        return TelegramResponse::failure($errorCode, $description, $retryAfter !== null ? (int) $retryAfter : null);
    }

    /** Never let the bot token leak into logs or exceptions (spec §4.7). */
    private function scrub(string $message): string
    {
        return str_replace($this->token, '[REDACTED-TOKEN]', $message);
    }
}
