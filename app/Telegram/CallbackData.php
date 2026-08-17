<?php

declare(strict_types=1);

namespace App\Telegram;

/**
 * Callback-data protocol (spec §9): `menu:{id}` · `join:check` ·
 * `bc:{broadcast_id}:{button_id}` · `invite:show`. Arbitrary strings are
 * never trusted — anything else parses to null.
 */
final class CallbackData
{
    // Telegram's own limit; longer payloads are hostile or corrupt.
    private const MAX_LENGTH = 64;

    public static function parse(?string $data): ?CallbackAction
    {
        if (! is_string($data) || $data === '' || strlen($data) > self::MAX_LENGTH) {
            return null;
        }

        if ($data === 'join:check') {
            return CallbackAction::joinCheck();
        }

        if ($data === 'invite:show') {
            return CallbackAction::inviteShow();
        }

        if (preg_match('/^menu:(\d{1,10})$/', $data, $m) === 1) {
            return CallbackAction::menu((int) $m[1]);
        }

        if (preg_match('/^bc:(\d{1,10}):(\d{1,10})$/', $data, $m) === 1) {
            return CallbackAction::broadcastButton((int) $m[1], (int) $m[2]);
        }

        return null;
    }
}
