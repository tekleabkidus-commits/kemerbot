<?php

declare(strict_types=1);

namespace App\Services\Broadcasts;

use App\Enums\BotLanguage;
use App\Enums\BroadcastType;
use App\Enums\MediaKind;
use App\Models\Broadcast;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SettingsService;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramRateLimiter;
use App\Services\Telegram\TelegramResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Wizard step 6 (spec §5.3): send the rendered broadcast to the configured
 * admin test recipients. Never touches counters, failures, or the snapshot.
 * Recipients get the EN rendering with a stand-in first name.
 */
final class BroadcastTestSender
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly BroadcastRenderer $renderer,
        private readonly TelegramClient $client,
        private readonly TelegramRateLimiter $limiter,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array{sent: int, recipients: int} */
    public function send(Broadcast $broadcast): array
    {
        $recipients = array_values(array_filter(array_map(
            'intval',
            (array) $this->settings->get('broadcast.test_recipient_chat_ids', []),
        )));

        $sent = 0;

        foreach ($recipients as $chatId) {
            $this->limiter->acquire();

            if ($broadcast->type === BroadcastType::Poll && $broadcast->poll !== null) {
                // Test polls create no poll_instances — never counted.
                $response = $this->client->sendPoll(
                    $chatId,
                    '[TEST] '.($broadcast->poll->question['en'] ?? '?'),
                    $broadcast->poll->options['en'] ?? [],
                    $broadcast->poll->is_anonymous,
                );
            } else {
                $standIn = new User(['first_name' => 'Test', 'language' => 'en']);
                $standIn->tg_chat_id = $chatId;

                $message = $this->renderer->renderForLanguage($broadcast, BotLanguage::En, $standIn);

                $response = $message->media === null
                    ? $this->client->sendText($chatId, '[TEST] '.$message->text, $message->replyMarkup)
                    : $this->sendMedia($chatId, $message);
            }

            if ($response->successful()) {
                $sent++;
            }
        }

        $this->audit->log('broadcast.test_sent', $broadcast, [
            'recipients' => count($recipients),
            'delivered' => $sent,
        ]);

        return ['sent' => $sent, 'recipients' => count($recipients)];
    }

    private function sendMedia(int $chatId, RenderedMessage $message): TelegramResponse
    {
        $media = $message->media;
        $payload = $media->tg_file_id ?? Storage::path($media->path);
        $caption = '[TEST] '.$message->text;

        return match ($media->kind) {
            MediaKind::Photo => $this->client->sendPhoto($chatId, $payload, $caption, $message->replyMarkup),
            MediaKind::Video => $this->client->sendVideo($chatId, $payload, $caption, $message->replyMarkup),
            MediaKind::Animation => $this->client->sendAnimation($chatId, $payload, $caption, $message->replyMarkup),
        };
    }
}
