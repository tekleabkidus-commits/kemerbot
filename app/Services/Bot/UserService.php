<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Models\TrackingLink;
use App\Models\User;
use App\Telegram\StartPayload;
use App\Telegram\StartPayloadType;

/**
 * Bot-audience lifecycle: create-or-update from Telegram, first-touch
 * attribution (spec §4.5), activity tracking and blocked handling (§4.11).
 */
final class UserService
{
    /**
     * Create or refresh a user from a Telegram `from`/`chat` object.
     *
     * @return array{0: User, 1: bool} [user, wasCreated]
     */
    public function upsertFromTelegram(array $from): array
    {
        $chatId = (int) $from['id'];

        $user = User::query()->where('tg_chat_id', $chatId)->first();

        if ($user === null) {
            $user = User::query()->create([
                'tg_chat_id' => $chatId,
                'first_name' => (string) ($from['first_name'] ?? ''),
                'username' => $from['username'] ?? null,
                'language' => $from['language_code'] ?? null,
                'joined_at' => now(),
                'last_active_at' => now(),
            ]);

            return [$user, true];
        }

        // Refresh profile fields if Telegram reports changes (spec §5.1).
        $user->fill([
            'first_name' => (string) ($from['first_name'] ?? $user->first_name),
            'username' => $from['username'] ?? null,
            'language' => $from['language_code'] ?? $user->language,
        ]);

        if ($user->isDirty()) {
            $user->save();
        }

        return [$user, false];
    }

    /**
     * First-touch, immutable attribution (spec §4.5): source and referrer are
     * set only when empty; a later /start never rewrites either. Self-referral
     * and unknown referrer ids are silently ignored.
     */
    public function applyAttribution(User $user, StartPayload $payload): void
    {
        if ($payload->type === StartPayloadType::Source && $user->source === null) {
            $user->update(['source' => $payload->source]);

            // Joins counted once, at first-touch attribution only (spec §5.6).
            TrackingLink::query()
                ->where('code', $payload->source)
                ->where('is_active', true)
                ->increment('joins_count');
        }

        if ($payload->type === StartPayloadType::Referral && $user->referred_by_user_id === null) {
            $referrer = User::query()->find($payload->referrerUserId);

            if ($referrer !== null && $referrer->id !== $user->id) {
                $user->update(['referred_by_user_id' => $referrer->id]);
            }
        }
    }

    /**
     * Every successful inbound interaction: bump last_active_at and clear the
     * blocked flag — they evidently unblocked us (spec §4.11).
     */
    public function recordActivity(User $user): void
    {
        $user->forceFill(['last_active_at' => now()]);

        if ($user->blocked_bot) {
            $user->forceFill(['blocked_bot' => false, 'blocked_at' => null]);
        }

        $user->save();
    }

    /** A send came back 403: the user blocked the bot (spec §5.1). */
    public function markBlocked(User $user): void
    {
        $user->forceFill(['blocked_bot' => true, 'blocked_at' => now()])->save();
    }
}
