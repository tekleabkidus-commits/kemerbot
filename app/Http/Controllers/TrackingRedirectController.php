<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TrackingLink;
use Illuminate\Http\RedirectResponse;

/**
 * /r/{code} click redirect (spec §4.6). Counts the click and 302s to OUR bot
 * deep link and nowhere else: the route constraint + CODE_PATTERN re-check
 * reject hostile codes with 404, and the Location header is built purely from
 * the configured bot username + the validated code — user input never becomes
 * a host, scheme, or path. No user identification happens here (anonymous
 * pre-Telegram click).
 */
class TrackingRedirectController extends Controller
{
    public function __invoke(string $code): RedirectResponse
    {
        abort_unless(preg_match(TrackingLink::CODE_PATTERN, $code) === 1, 404);

        $link = TrackingLink::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        abort_if($link === null, 404);

        $botUsername = (string) config('telegram.bot_username');
        abort_if($botUsername === '', 404);

        $link->increment('clicks_count');

        return redirect()->away(
            'https://t.me/'.$botUsername.'?start='.$code,
            302,
        );
    }
}
