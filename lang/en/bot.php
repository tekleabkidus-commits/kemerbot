<?php

declare(strict_types=1);

// Fixed bot UI strings. Content strings (menus, replies, broadcasts) live in
// the DB with per-language translations; these are the structural bits only.
return [
    'menu_prompt' => 'Choose an option:',
    'back' => '⬅️ Back',
    'join_gate' => "To use the SunBet bot, please join our channel first. 📣\nTap \"Join channel\", then \"I've joined ✅\".",
    'join_button' => 'Join channel',
    'joined_check_button' => "I've joined ✅",
    'joined_ok' => "Welcome aboard! 🎉 You're all set.",
    'not_member_yet' => "It looks like you haven't joined yet. Tap \"Join channel\" first, then try again.",
    'option_unavailable' => 'This option is no longer available.',
    'welcome_fallback' => 'Welcome to SunBet, {first_name}! ⚽',
];
