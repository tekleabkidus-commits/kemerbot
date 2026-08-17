<?php

declare(strict_types=1);

use App\Telegram\TelegramWebhookProcessor;

/**
 * The webhook registration (telegram:set-webhook → setWebhook allowed_updates)
 * must subscribe to every update type TelegramWebhookProcessor actually
 * handles — otherwise a handled feature silently never receives events.
 */
it('subscribes to every update type the processor handles', function () {
    // Update types with real handling paths in TelegramWebhookProcessor::process().
    $handled = ['message', 'callback_query', 'my_chat_member', 'poll', 'poll_answer'];

    $allowed = config('telegram.allowed_updates');

    expect(array_diff($handled, $allowed))->toBe([]);
});

it('keeps the handled-types list in sync with the processor source', function () {
    // Guard against the test list above rotting: every $update['<type>'] key
    // the processor inspects must appear in allowed_updates.
    $source = file_get_contents((new ReflectionClass(TelegramWebhookProcessor::class))->getFileName());

    preg_match_all("/\\\$update\['([a-z_]+)'\]/", $source, $matches);
    $inspected = array_values(array_diff(array_unique($matches[1]), ['update_id']));

    expect($inspected)->not->toBe([])
        ->and(array_diff($inspected, config('telegram.allowed_updates')))->toBe([]);
});
