<?php

namespace App\Services\Bot;

use App\Models\User;

final class PreferencesHandler
{
    public function handle(User $user, string $text): bool
    {
        $command = strtolower(trim($text));
        $locale = app(BotLocaleResolver::class);
        $lang = $locale->resolve($user);
        $message = null;
        if (in_array($command, ['/stop', '/unsubscribe'], true)) {
            $user->update(['marketing_subscribed' => false]);
            $message = $locale->uiText('unsubscribed', $lang);
        } elseif ($command === '/subscribe') {
            $user->update(['marketing_subscribed' => true]);
            $message = $locale->uiText('subscribed', $lang);
        } elseif (preg_match('/^\/language (en|am)$/', $command, $m)) {
            $user->update(['preferred_language' => $m[1]]);
            $message = $locale->uiText('preferences_saved', $locale->resolve($user));
        } elseif (preg_match('/^\/topics (general|matches|offers|news)(,(general|matches|offers|news))*$/', $command)) {
            $user->update(['topics' => array_values(array_unique(explode(',', substr($command, 8))))]);
            $message = $locale->uiText('preferences_saved', $lang);
        } elseif (preg_match('/^\/frequency ([1-9])$/', $command, $m)) {
            $user->update(['daily_message_limit' => (int) $m[1]]);
            $message = $locale->uiText('preferences_saved', $lang);
        } elseif ($command === '/preferences') {
            $message = $locale->uiText('preferences_help', $lang);
        }
        if ($message === null) {
            return false;
        }app(BotMessageSender::class)->sendToUser($user, $message);

        return true;
    }
}
