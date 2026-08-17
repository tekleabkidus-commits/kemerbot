<?php

declare(strict_types=1);

namespace App\Telegram\Handlers;

use App\Enums\BotLanguage;
use App\Enums\MenuActionType;
use App\Models\Broadcast;
use App\Models\ButtonClick;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\Bot\BotLocaleResolver;
use App\Services\Bot\BotMessageSender;
use App\Services\Bot\MenuRenderer;
use App\Services\Bot\UserService;
use App\Services\Bot\WelcomeService;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramMembershipService;
use App\Telegram\CallbackAction;
use App\Telegram\CallbackActionType;
use App\Telegram\CallbackData;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Callback queries (spec §9): validate the payload, then act. Telegram's
 * spinner is ALWAYS answered — even for hostile or stale payloads.
 */
final class CallbackQueryHandler
{
    public function __construct(
        private readonly UserService $users,
        private readonly TelegramClient $client,
        private readonly TelegramMembershipService $membership,
        private readonly WelcomeService $welcome,
        private readonly MenuRenderer $menus,
        private readonly BotLocaleResolver $locale,
        private readonly BotMessageSender $sender,
    ) {}

    public function handle(array $callbackQuery): void
    {
        $callbackId = (string) ($callbackQuery['id'] ?? '');
        $from = $callbackQuery['from'] ?? null;

        if ($callbackId === '' || ! is_array($from)) {
            return;
        }

        [$user] = $this->users->upsertFromTelegram($from);
        $this->users->recordActivity($user);

        $action = CallbackData::parse($callbackQuery['data'] ?? null);

        if ($action === null) {
            // Unknown/hostile payload: release the spinner, log, do nothing else.
            Log::info('telegram.callback.invalid', ['user_id' => $user->id]);
            $this->client->answerCallbackQuery($callbackId);

            return;
        }

        try {
            $this->dispatch($user, $action, $callbackId);
        } catch (Throwable $e) {
            // Never leave a spinner hanging, whatever went wrong.
            $this->client->answerCallbackQuery($callbackId);

            throw $e;
        }
    }

    private function dispatch(User $user, CallbackAction $action, string $callbackId): void
    {
        match ($action->type) {
            CallbackActionType::Menu => $this->handleMenu($user, $action->menuItemId, $callbackId),
            CallbackActionType::JoinCheck => $this->handleJoinCheck($user, $callbackId),
            CallbackActionType::BroadcastButton => $this->handleBroadcastButton($user, $action, $callbackId),
            CallbackActionType::InviteShow => $this->handleInviteShow($callbackId),
        };
    }

    private function handleMenu(User $user, int $menuItemId, string $callbackId): void
    {
        $lang = $this->locale->resolve($user);

        if (! $this->membership->isMember($user)) {
            $this->client->answerCallbackQuery($callbackId);
            $this->welcome->sendJoinGate($user);

            return;
        }

        if ($menuItemId === MenuRenderer::ROOT) {
            $this->client->answerCallbackQuery($callbackId);
            $this->sender->sendToUser(
                $user,
                $this->locale->uiText('menu_prompt', $lang),
                replyMarkup: $this->menus->rootKeyboard($lang),
            );

            return;
        }

        $item = MenuItem::query()->where('is_active', true)->with('translations')->find($menuItemId);

        if ($item === null) {
            $this->client->answerCallbackQuery(
                $callbackId,
                $this->locale->uiText('option_unavailable', $lang),
            );

            return;
        }

        $this->client->answerCallbackQuery($callbackId);

        match ($item->action_type) {
            MenuActionType::Reply => $this->sendReply($user, $item, $lang),
            MenuActionType::Submenu => $this->sender->sendToUser(
                $user,
                $this->locale->pickTranslation($item->translations, $lang)?->label
                    ?? $this->locale->uiText('menu_prompt', $lang),
                replyMarkup: $this->menus->submenuKeyboard($item, $lang),
            ),
            // URL/Web App actions are buttons, not callbacks — a callback here is stale/hostile.
            MenuActionType::Url, MenuActionType::Webapp => null,
        };
    }

    private function sendReply(User $user, MenuItem $item, BotLanguage $lang): void
    {
        $translation = $this->locale->pickTranslation($item->translations, $lang);

        if ($translation === null || ($translation->reply_text === null && $item->media_file_id === null)) {
            return;
        }

        $this->sender->sendToUser($user, $translation->reply_text, $item->mediaFile);
    }

    private function handleJoinCheck(User $user, string $callbackId): void
    {
        $lang = $this->locale->resolve($user);

        if ($this->membership->isMember($user, forceRefresh: true)) {
            $this->client->answerCallbackQuery($callbackId, $this->locale->uiText('joined_ok', $lang));
            $this->welcome->sendWelcome($user);

            return;
        }

        $this->client->answerCallbackQuery(
            $callbackId,
            $this->locale->uiText('not_member_yet', $lang),
            showAlert: true,
        );
    }

    private function handleBroadcastButton(User $user, CallbackAction $action, string $callbackId): void
    {
        $broadcast = Broadcast::query()->find($action->broadcastId);
        $button = $broadcast?->buttons()->whereKey($action->buttonId)->first();

        if ($broadcast !== null && $button !== null) {
            ButtonClick::query()->create([
                'user_id' => $user->id,
                'broadcast_id' => $broadcast->id,
                'button_id' => $button->id,
                'clicked_at' => now(),
            ]);
        }

        $this->client->answerCallbackQuery($callbackId);
    }

    private function handleInviteShow(string $callbackId): void
    {
        // Referral-lite ships in Batch 4; until then just release the spinner.
        Log::info('telegram.callback.invite_show_deferred');
        $this->client->answerCallbackQuery($callbackId);
    }
}
