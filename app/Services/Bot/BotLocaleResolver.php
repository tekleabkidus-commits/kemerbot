<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Enums\BotLanguage;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Collection;

/**
 * THE single place bot language resolution and EN-fallback happen (spec §4.12).
 * Every user-facing bot string flows through one of these methods.
 */
final class BotLocaleResolver
{
    public function __construct(private readonly SettingsService $settings) {}

    public function resolve(User $user): BotLanguage
    {
        $lang = strtolower((string) $user->language);

        if (str_starts_with($lang, 'am')) {
            return BotLanguage::Am;
        }

        if (str_starts_with($lang, 'en')) {
            return BotLanguage::En;
        }

        // Unknown or missing Telegram language → admin-configured default.
        $default = (string) $this->settings->get('bot.default_language', BotLanguage::En->value);

        return BotLanguage::tryFrom($default) ?? BotLanguage::En;
    }

    /**
     * Pick a `_translations` row for a language, falling back to English.
     *
     * @param  Collection<int, covariant \Illuminate\Database\Eloquent\Model>  $translations
     */
    public function pickTranslation(Collection $translations, BotLanguage $lang): ?object
    {
        return $translations->firstWhere('lang', $lang)
            ?? $translations->firstWhere('lang', BotLanguage::En);
    }

    /** Pick from a jsonb {en, am} language map, falling back to English. */
    public function pickFromMap(?array $map, BotLanguage $lang): ?string
    {
        $value = $map[$lang->value] ?? null;

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        $fallback = $map[BotLanguage::En->value] ?? null;

        return is_string($fallback) && trim($fallback) !== '' ? $fallback : null;
    }

    /** Fixed UI strings from lang/{en,am}/bot.php, falling back to English. */
    public function uiText(string $key, BotLanguage $lang): string
    {
        $line = __('bot.'.$key, [], $lang->value);

        if ($line === 'bot.'.$key) {
            $line = __('bot.'.$key, [], BotLanguage::En->value);
        }

        return $line;
    }
}
