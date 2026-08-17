<?php

declare(strict_types=1);

namespace App\Telegram\Handlers;

use App\Events\UserJoined;
use App\Services\Bot\InboundMessageRecorder;
use App\Services\Bot\UserService;
use App\Services\Bot\WelcomeService;
use App\Services\Telegram\TelegramMembershipService;
use App\Telegram\StartPayloadParser;

/**
 * /start flow (spec §9): upsert → parse payload → attribution → activity →
 * UserJoined (new users only) → membership gate or welcome + menu.
 * Fully idempotent on repeat: no duplicate users, no attribution rewrites,
 * no duplicate UserJoined.
 */
final class StartCommandHandler
{
    public function __construct(
        private readonly UserService $users,
        private readonly StartPayloadParser $parser,
        private readonly InboundMessageRecorder $inbound,
        private readonly TelegramMembershipService $membership,
        private readonly WelcomeService $welcome,
    ) {}

    public function handle(array $message): void
    {
        $from = $message['from'] ?? null;

        if (! is_array($from) || ($from['is_bot'] ?? false) || (($message['chat']['type'] ?? 'private') !== 'private')) {
            return;
        }

        [$user, $wasCreated] = $this->users->upsertFromTelegram($from);

        $payload = $this->parser->parse($this->payloadFrom((string) ($message['text'] ?? '')));
        $this->users->applyAttribution($user, $payload);
        $this->users->recordActivity($user);
        $this->inbound->record($user, $message);

        if ($wasCreated) {
            UserJoined::dispatch($user);
        }

        if (! $this->membership->isMember($user)) {
            $this->welcome->sendJoinGate($user);

            return;
        }

        $this->welcome->sendWelcome($user);
    }

    private function payloadFrom(string $text): ?string
    {
        // "/start" or "/start <payload>" — anything after the first space.
        $parts = explode(' ', trim($text), 2);

        return $parts[1] ?? null;
    }
}
