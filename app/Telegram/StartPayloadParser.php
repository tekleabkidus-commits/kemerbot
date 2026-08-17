<?php

declare(strict_types=1);

namespace App\Telegram;

/**
 * THE single /start payload parser (Principle 2). Handles source codes,
 * ref_<id> referrals, and invalid/malformed/unknown payloads. No other code
 * path may interpret /start payloads.
 */
final class StartPayloadParser
{
    // Matches tracking-link code rules (spec §5.6): safe charset, bounded length.
    private const SOURCE_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

    private const REFERRAL_PATTERN = '/^ref_(\d{1,18})$/';

    public function parse(?string $raw): StartPayload
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return StartPayload::none();
        }

        if (preg_match(self::REFERRAL_PATTERN, $raw, $m) === 1) {
            return StartPayload::referral((int) $m[1]);
        }

        // A ref_-prefixed payload that isn't a well-formed referral is invalid,
        // never a source code (e.g. "ref_abc", "ref_").
        if (str_starts_with($raw, 'ref_') || $raw === 'ref') {
            return StartPayload::invalid();
        }

        if (preg_match(self::SOURCE_PATTERN, $raw) === 1) {
            return StartPayload::source($raw);
        }

        return StartPayload::invalid();
    }
}
