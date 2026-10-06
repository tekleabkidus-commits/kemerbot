<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Enums\KeywordMatchType;
use App\Models\KeywordReply;
use Illuminate\Support\Facades\Cache;
use Normalizer;

/**
 * Deterministic keyword matching (spec §4.9): exact match wins, then the
 * longest `contains` match, then lowest position. Input is normalized —
 * trimmed, case-folded, Unicode NFC — so Amharic input matches reliably.
 */
final class KeywordMatcher
{
    public function match(string $input): ?KeywordReply
    {
        $normalized = $this->normalize($input);

        if ($normalized === '') {
            return null;
        }

        $rules = Cache::remember('bot:keyword-rules', 60, fn () => KeywordReply::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->with(['translations', 'mediaFile'])->get());

        foreach ($rules as $rule) {
            if ($rule->match_type === KeywordMatchType::Exact) {
                foreach ($rule->keywords as $keyword) {
                    if ($this->normalize((string) $keyword) === $normalized) {
                        return $rule;
                    }
                }
            }
        }

        $best = null;
        $bestLength = 0;

        foreach ($rules as $rule) {
            if ($rule->match_type !== KeywordMatchType::Contains) {
                continue;
            }

            foreach ($rule->keywords as $keyword) {
                $needle = $this->normalize((string) $keyword);

                if ($needle === '' || ! str_contains($normalized, $needle)) {
                    continue;
                }

                $length = mb_strlen($needle);

                // Strictly longer wins; ties keep the earlier rule (lowest position).
                if ($length > $bestLength) {
                    $best = $rule;
                    $bestLength = $length;
                }
            }
        }

        return $best;
    }

    public function normalize(string $text): string
    {
        $text = trim($text);

        $normalized = Normalizer::normalize($text, Normalizer::FORM_C);

        if (is_string($normalized)) {
            $text = $normalized;
        }

        return mb_strtolower($text);
    }
}
