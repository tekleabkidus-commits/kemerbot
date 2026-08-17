<?php

declare(strict_types=1);

namespace App\Services\Broadcasts;

use App\Enums\BotLanguage;
use App\Enums\BroadcastType;
use App\Enums\ButtonKind;
use App\Models\Broadcast;
use App\Models\User;
use App\Services\Bot\BotLocaleResolver;
use App\Services\Bot\TokenRenderer;
use Illuminate\Support\Carbon;

/**
 * Renders one broadcast for one user: locale pick with EN fallback,
 * {token} personalization, inline keyboard with click-tracked callbacks,
 * and the structured match-promo caption (spec §4.8: kickoff in Addis time).
 */
final class BroadcastRenderer
{
    public function __construct(
        private readonly BotLocaleResolver $locale,
        private readonly TokenRenderer $tokens,
    ) {}

    public function render(Broadcast $broadcast, User $user): RenderedMessage
    {
        $lang = $this->locale->resolve($user);

        return $this->renderForLanguage($broadcast, $lang, $user);
    }

    /** Also used by test sends and the wizard preview, with a stand-in user. */
    public function renderForLanguage(Broadcast $broadcast, BotLanguage $lang, User $user): RenderedMessage
    {
        $translation = $this->locale->pickTranslation($broadcast->translations, $lang);

        $text = match ($broadcast->type) {
            BroadcastType::MatchCard => $this->matchCardCaption($broadcast, $translation?->text, $lang),
            default => $translation?->text,
        };

        if ($text !== null) {
            $text = $this->tokens->renderForUser($text, $user);
        }

        return new RenderedMessage(
            text: $text,
            media: $translation?->mediaFile,
            replyMarkup: $this->keyboard($broadcast, $lang),
        );
    }

    private function keyboard(Broadcast $broadcast, BotLanguage $lang): ?array
    {
        if ($broadcast->buttons->isEmpty()) {
            return null;
        }

        $rows = [];

        foreach ($broadcast->buttons as $button) {
            $label = $this->locale->pickTranslation($button->translations, $lang)?->label ?? '—';

            $rows[$button->row][] = $button->kind === ButtonKind::Url
                ? ['text' => $label, 'url' => (string) $button->url]
                : ['text' => $label, 'callback_data' => "bc:{$broadcast->id}:{$button->id}"];
        }

        ksort($rows);

        return ['inline_keyboard' => array_values($rows)];
    }

    /**
     * Match promo card (spec §4.8, Option A): admin image + consistently
     * formatted caption. No server-side image generation.
     */
    private function matchCardCaption(Broadcast $broadcast, ?string $extraText, BotLanguage $lang): string
    {
        $fields = $broadcast->template_fields ?? [];

        $lines = [];

        $home = $fields['home_team'] ?? '?';
        $away = $fields['away_team'] ?? '?';
        $lines[] = "⚽ {$home} vs {$away}";

        if (! empty($fields['kickoff_at'])) {
            $kickoff = Carbon::parse($fields['kickoff_at'])
                ->timezone(config('app.display_timezone'))
                ->format('D d M · H:i');
            $lines[] = "🕒 {$kickoff}";
        }

        $odds = $fields['odds'] ?? null;

        if (is_array($odds) && $odds !== []) {
            $lines[] = sprintf(
                '1️⃣ %s   ✖️ %s   2️⃣ %s',
                $odds['home'] ?? '-',
                $odds['draw'] ?? '-',
                $odds['away'] ?? '-',
            );
        }

        if (is_string($extraText) && trim($extraText) !== '') {
            $lines[] = '';
            $lines[] = $extraText;
        }

        if (! empty($fields['cta'])) {
            $lines[] = '';
            $lines[] = '👉 '.$fields['cta'];
        }

        return implode("\n", $lines);
    }
}
