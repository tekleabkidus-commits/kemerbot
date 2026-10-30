<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Enums\BotLanguage;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * BetnaBot is English-only. Legacy translations remain in the database so
 * upgrades do not destroy existing content; all runtime output uses English.
 */
final class BotLocaleResolver
{
    public function resolve(User $user): BotLanguage
    {
        return BotLanguage::En;
    }

    /**
     * @param Collection<int, covariant \Illuminate\Database\Eloquent\Model> $translations
     */
    public function pickTranslation(Collection $translations, BotLanguage $lang): ?object
    {
        return $translations->firstWhere('lang', BotLanguage::En);
    }

    public function pickFromMap(?array $map, BotLanguage $lang): ?string
    {
        $value = $map[BotLanguage::En->value] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    public function uiText(string $key, BotLanguage $lang): string
    {
        return __('bot.'.$key, [], BotLanguage::En->value);
    }
}
