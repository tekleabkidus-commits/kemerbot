<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Enums\BotLanguage;

/**
 * Renders the embedded jsonb button shape used by keyword replies and
 * automation steps — rows of {kind, url, label: {en, am}} — into a Telegram
 * inline keyboard. URL kind only; labels resolve through BotLocaleResolver.
 */
final class EmbeddedButtonsRenderer
{
    public function __construct(private readonly BotLocaleResolver $locale) {}

    public function render(?array $buttons, BotLanguage $lang): ?array
    {
        $rows = [];

        foreach ($buttons ?? [] as $button) {
            if (($button['kind'] ?? null) !== 'url' || empty($button['url'])) {
                continue;
            }

            $label = $this->locale->pickFromMap($button['label'] ?? null, $lang);

            if ($label !== null) {
                $rows[] = [['text' => $label, 'url' => $button['url']]];
            }
        }

        return $rows === [] ? null : ['inline_keyboard' => $rows];
    }
}
