<?php

declare(strict_types=1);

// Fixed bot UI strings. Content strings (menus, replies, broadcasts) live in
// the DB with per-language translations; these are the structural bits only.
return [
    'menu_prompt' => 'Choose an option:',
    'back' => '⬅️ Back',
    'join_gate' => "To use the KemerBet bot, please join our channel first. 📣\nTap \"Join channel\", then \"I've joined ✅\".",
    'join_button' => 'Join channel',
    'joined_check_button' => "I've joined ✅",
    'joined_ok' => "Welcome aboard! 🎉 You're all set.",
    'not_member_yet' => "It looks like you haven't joined yet. Tap \"Join channel\" first, then try again.",
    'option_unavailable' => 'This option is no longer available.',
    'welcome_fallback' => 'Welcome to KemerBet, {first_name}! ⚽',
    'invite_text' => "Invite your friends to KemerBet! 🎉\nShare your personal link:\n:link",
    'unsubscribed' => 'You have paused promotional messages. Use /subscribe to receive them again.',
    'subscribed' => 'You are subscribed. Use /preferences to choose what you receive.',
    'preferences_saved' => 'Your preferences are saved.',
    'preferences_help' => 'Your messages, your choice.\n/stop — pause promotions\n/subscribe — receive promotions\n/language en or /language am\n/topics general,matches,offers,news\n/frequency 1 — maximum promotions per day',
];
