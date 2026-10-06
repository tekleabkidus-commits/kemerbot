<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Bot credentials (env only — never stored in DB, UI, logs, or audit meta)
    |--------------------------------------------------------------------------
    */

    'conversion_api_key' => env('CONVERSION_API_KEY'),
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'bot_username' => env('TELEGRAM_BOT_USERNAME'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),

    'api_base_url' => env('TELEGRAM_API_BASE_URL', 'https://api.telegram.org'),

    /*
    |--------------------------------------------------------------------------
    | Sending
    |--------------------------------------------------------------------------
    | Global send rate (messages/second) enforced by the Redis token bucket
    | across all workers. The DB setting `telegram.send_rate` may lower it at
    | runtime; this env value is the default and the hard ceiling.
    */

    'send_rate' => (int) env('TELEGRAM_SEND_RATE', 25),

    // Broadcast chunk size: users per sender job batch (spec §4.14: ~25–50,
    // one atomic counter UPDATE per chunk).
    'broadcast_chunk_size' => (int) env('TELEGRAM_BROADCAST_CHUNK_SIZE', 25),

    // Retry policy for retryable send failures (429 / transient network).
    'send_max_attempts' => (int) env('TELEGRAM_SEND_MAX_ATTEMPTS', 3),
    'send_backoff_seconds' => [5, 30, 120],

    /*
    |--------------------------------------------------------------------------
    | Membership checks
    |--------------------------------------------------------------------------
    | A cached membership result older than this TTL is re-checked on the
    | user's next interaction (spec §5.1).
    */

    'membership_ttl_minutes' => (int) env('TELEGRAM_MEMBERSHIP_TTL_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    */

    // Redis SETNX TTL for update_id dedupe.
    'update_dedupe_ttl_seconds' => (int) env('TELEGRAM_UPDATE_DEDUPE_TTL', 600),

    'allowed_updates' => ['message', 'callback_query', 'poll', 'poll_answer', 'my_chat_member'],

    /*
    |--------------------------------------------------------------------------
    | Queues (spec §10) — interactive traffic never waits behind a broadcast
    |--------------------------------------------------------------------------
    */

    'queues' => [
        'interactive' => 'telegram-interactive',
        'broadcast' => 'telegram-broadcast',
        'automation' => 'automation',
    ],

    /*
    |--------------------------------------------------------------------------
    | Data retention
    |--------------------------------------------------------------------------
    */

    'message_retention_days' => (int) env('TELEGRAM_MESSAGE_RETENTION_DAYS', 90),

    // Orphaned broadcast audience snapshot keys older than this are swept.
    'snapshot_orphan_hours' => (int) env('TELEGRAM_SNAPSHOT_ORPHAN_HOURS', 48),

    // A recipient claimed by a worker (stream pending entry) longer than this
    // is considered abandoned and reclaimed by another worker.
    'broadcast_claim_timeout_seconds' => (int) env('TELEGRAM_BROADCAST_CLAIM_TIMEOUT', 720),

    // A `sending` broadcast with no counter progress for this long gets its
    // chunk chain re-dispatched by broadcasts:recover-stalled.
    'broadcast_stall_seconds' => (int) env('TELEGRAM_BROADCAST_STALL_SECONDS', 780),

    // Automation outbox recovery: re-dispatch queued deliveries idle longer
    // than this; reset `sending` deliveries idle longer than 2x this.
    'automation_delivery_stall_seconds' => (int) env('TELEGRAM_AUTOMATION_STALL_SECONDS', 300),

    // Per-instance previous poll counts (delta computation) live this long.
    'poll_prev_ttl_days' => (int) env('TELEGRAM_POLL_PREV_TTL_DAYS', 30),

];
