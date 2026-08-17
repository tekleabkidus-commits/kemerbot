<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Log;

/**
 * Channel-membership checks (spec §5.1). Normalizes Telegram member states,
 * caches on the user row with a TTL, and degrades gracefully: a Telegram error
 * never locks users out (fail-open with a logged warning). The bot must be an
 * admin of the channel for reliable getChatMember (documented in README).
 */
final class TelegramMembershipService
{
    private const MEMBER_STATES = ['creator', 'administrator', 'member'];

    public function __construct(
        private readonly TelegramClient $client,
        private readonly SettingsService $settings,
    ) {}

    public function isMember(User $user, bool $forceRefresh = false): bool
    {
        $channelId = $this->settings->get('channel.id');

        // No channel configured → no join gate.
        if ($channelId === null || $channelId === '') {
            return true;
        }

        $ttlMinutes = (int) config('telegram.membership_ttl_minutes');

        if (! $forceRefresh
            && $user->channel_checked_at !== null
            && $user->channel_checked_at->gt(now()->subMinutes($ttlMinutes))) {
            return $user->in_channel;
        }

        $response = $this->client->getChatMember($channelId, $user->tg_chat_id);

        if (! $response->successful()) {
            Log::warning('telegram.membership_check_failed', [
                'user_id' => $user->id,
                'error_code' => $response->errorCode,
                'description' => $response->description,
            ]);

            // Fail-open: last known state if we ever checked, otherwise let them in.
            return $user->channel_checked_at !== null ? $user->in_channel : true;
        }

        $status = is_array($response->result) ? ($response->result['status'] ?? 'left') : 'left';

        $isMember = in_array($status, self::MEMBER_STATES, true)
            || ($status === 'restricted' && (bool) ($response->result['is_member'] ?? false));

        $user->forceFill([
            'in_channel' => $isMember,
            'channel_checked_at' => now(),
        ])->save();

        return $isMember;
    }
}
