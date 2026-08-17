<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Models\MediaFile;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\Telegram\TelegramResponse;

/**
 * Sends the admin-editable welcome (THE /start reply, spec §4.2) with the main
 * menu attached, and the force-join gate (spec §5.1).
 */
final class WelcomeService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly BotLocaleResolver $locale,
        private readonly TokenRenderer $tokens,
        private readonly MenuRenderer $menus,
        private readonly BotMessageSender $sender,
    ) {}

    public function sendWelcome(User $user): TelegramResponse
    {
        $lang = $this->locale->resolve($user);

        $template = $this->locale->pickFromMap(
            $this->settings->get('welcome.message'),
            $lang,
        ) ?? $this->locale->uiText('welcome_fallback', $lang);

        $text = $this->tokens->renderForUser($template, $user);

        $mediaId = $this->settings->get('welcome.media_file_id');
        $media = $mediaId !== null ? MediaFile::query()->find($mediaId) : null;

        return $this->sender->sendToUser($user, $text, $media, $this->menus->rootKeyboard($lang));
    }

    public function sendJoinGate(User $user): TelegramResponse
    {
        $lang = $this->locale->resolve($user);

        $rows = [];
        $channelUrl = $this->settings->get('channel.url');

        if (is_string($channelUrl) && $channelUrl !== '') {
            $rows[] = [['text' => $this->locale->uiText('join_button', $lang), 'url' => $channelUrl]];
        }

        $rows[] = [['text' => $this->locale->uiText('joined_check_button', $lang), 'callback_data' => 'join:check']];

        return $this->sender->sendToUser(
            $user,
            $this->locale->uiText('join_gate', $lang),
            replyMarkup: ['inline_keyboard' => $rows],
        );
    }
}
