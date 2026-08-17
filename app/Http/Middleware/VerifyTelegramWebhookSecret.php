<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects webhook calls without the correct X-Telegram-Bot-Api-Secret-Token
 * fast, before any parsing (spec §9). Constant-time comparison; the secret
 * itself is never logged.
 */
class VerifyTelegramWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('telegram.webhook_secret');
        $provided = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (! is_string($expected) || $expected === ''
            || ! is_string($provided)
            || ! hash_equals($expected, $provided)) {
            abort(403);
        }

        return $next($request);
    }
}
