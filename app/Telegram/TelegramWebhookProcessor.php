<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Models\User;
use App\Services\Bot\PollService;
use App\Services\Bot\UserService;
use App\Telegram\Handlers\CallbackQueryHandler;
use App\Telegram\Handlers\IncomingMessageHandler;
use App\Telegram\Handlers\StartCommandHandler;
use Illuminate\Support\Facades\Log;

/**
 * Routes a verified, deduplicated Telegram update to its handler (spec §9).
 * Unknown update types are logged (structured, secret-free) and ignored.
 */
final class TelegramWebhookProcessor
{
    public function __construct(
        private readonly StartCommandHandler $startHandler,
        private readonly IncomingMessageHandler $messageHandler,
        private readonly CallbackQueryHandler $callbackHandler,
        private readonly UserService $users,
        private readonly PollService $polls,
    ) {}

    public function process(array $update): void
    {
        if (isset($update['message']) && is_array($update['message'])) {
            $this->routeMessage($update['message']);

            return;
        }

        if (isset($update['callback_query']) && is_array($update['callback_query'])) {
            $this->callbackHandler->handle($update['callback_query']);

            return;
        }

        if (isset($update['my_chat_member']) && is_array($update['my_chat_member'])) {
            $this->handleMyChatMember($update['my_chat_member']);

            return;
        }

        if (isset($update['poll']) && is_array($update['poll'])) {
            $this->polls->ingestPollUpdate($update['poll']);

            return;
        }

        if (isset($update['poll_answer']) && is_array($update['poll_answer'])) {
            $this->polls->ingestPollAnswer($update['poll_answer']);

            return;
        }

        Log::info('telegram.update.unhandled_type', [
            'update_id' => $update['update_id'] ?? null,
            'keys' => array_values(array_diff(array_keys($update), ['update_id'])),
        ]);
    }

    private function routeMessage(array $message): void
    {
        $text = $message['text'] ?? '';

        if (is_string($text) && preg_match('/^\/start(\s|$|@)/', trim($text)) === 1) {
            $this->startHandler->handle($message);

            return;
        }

        $this->messageHandler->handle($message);
    }

    /**
     * Private-chat my_chat_member updates tell us immediately when a user
     * blocks (kicked) or unblocks (member) the bot — cheaper than waiting
     * for a failed send (spec §5.1, §4.11).
     */
    private function handleMyChatMember(array $memberUpdate): void
    {
        if (($memberUpdate['chat']['type'] ?? null) !== 'private') {
            return;
        }

        $chatId = $memberUpdate['chat']['id'] ?? null;
        $status = $memberUpdate['new_chat_member']['status'] ?? null;

        if ($chatId === null || $status === null) {
            return;
        }

        $user = User::query()->where('tg_chat_id', (int) $chatId)->first();

        if ($user === null) {
            return;
        }

        if ($status === 'kicked') {
            $this->users->markBlocked($user);
        } elseif ($status === 'member') {
            $this->users->recordActivity($user);
        }
    }
}
