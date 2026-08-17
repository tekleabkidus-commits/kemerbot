<?php

declare(strict_types=1);

namespace App\Services\Telegram;

/**
 * The only doorway to the Telegram Bot API (spec §10). No Telegram HTTP from
 * controllers, Filament resources, models, or ad-hoc job code — ever.
 *
 * Media params ($photo/$video/$animation) accept a Telegram file_id, an HTTPS
 * URL, or an absolute local file path (uploaded as multipart).
 */
interface TelegramClient
{
    public function sendText(int $chatId, string $text, ?array $replyMarkup = null, ?string $parseMode = null): TelegramResponse;

    public function sendPhoto(int $chatId, string $photo, ?string $caption = null, ?array $replyMarkup = null, ?string $parseMode = null): TelegramResponse;

    public function sendVideo(int $chatId, string $video, ?string $caption = null, ?array $replyMarkup = null, ?string $parseMode = null): TelegramResponse;

    public function sendAnimation(int $chatId, string $animation, ?string $caption = null, ?array $replyMarkup = null, ?string $parseMode = null): TelegramResponse;

    /** @param list<string> $options */
    public function sendPoll(int $chatId, string $question, array $options, bool $isAnonymous = true): TelegramResponse;

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): TelegramResponse;

    public function getChatMember(int|string $chatId, int $userId): TelegramResponse;

    public function editMessageText(int $chatId, int $messageId, string $text, ?array $replyMarkup = null, ?string $parseMode = null): TelegramResponse;

    public function editMessageReplyMarkup(int $chatId, int $messageId, ?array $replyMarkup = null): TelegramResponse;

    public function setWebhook(string $url, string $secret, array $allowedUpdates): TelegramResponse;

    public function getWebhookInfo(): TelegramResponse;
}
