<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Enums\MessageDirection;
use App\Models\TelegramMessage;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Captures inbound messages for the user profile conversation view
 * (spec §5.7). Inbound + admin 1:1 only — never broadcast copies.
 */
final class InboundMessageRecorder
{
    private const MEDIA_TYPES = ['photo', 'video', 'animation', 'document', 'voice', 'sticker', 'audio', 'video_note'];

    public function record(User $user, array $message): TelegramMessage
    {
        [$type, $mediaMeta] = $this->classify($message);

        return TelegramMessage::query()->create([
            'user_id' => $user->id,
            'tg_message_id' => $message['message_id'] ?? null,
            'direction' => MessageDirection::Inbound,
            'type' => $type,
            'text' => $message['text'] ?? $message['caption'] ?? null,
            'media_meta' => $mediaMeta,
            'sent_at' => isset($message['date'])
                ? Carbon::createFromTimestampUTC((int) $message['date'])
                : now(),
        ]);
    }

    /** @return array{0: string, 1: ?array} */
    private function classify(array $message): array
    {
        if (isset($message['text'])) {
            return ['text', null];
        }

        foreach (self::MEDIA_TYPES as $type) {
            if (! isset($message[$type])) {
                continue;
            }

            $media = $message[$type];

            // Photos arrive as an array of sizes; keep the largest.
            if ($type === 'photo' && is_array($media) && array_is_list($media)) {
                $media = end($media) ?: [];
            }

            $meta = array_intersect_key(
                is_array($media) ? $media : [],
                array_flip(['file_id', 'file_unique_id', 'width', 'height', 'duration', 'mime_type', 'file_size']),
            );

            return [$type, $meta === [] ? null : $meta];
        }

        return ['other', null];
    }
}
