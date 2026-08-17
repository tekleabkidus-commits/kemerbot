<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MenuActionType;
use App\Models\Admin;
use App\Models\KeywordReply;
use App\Models\MenuItem;
use App\Services\SettingsService;
use Illuminate\Console\Command;

/**
 * One-off data fix for databases seeded before the KemerBet rebrand: rewrites
 * SunBet/sunbet.et branding in existing settings, menu items, keyword replies
 * and the seeded owner email, and retargets the product URLs. Idempotent —
 * safe to run on an already-clean database. Intended for the local dev DB and
 * the Laravel Cloud Commands console.
 */
class RebrandToKemerBet extends Command
{
    protected $signature = 'kemerbot:rebrand-kemerbet';

    protected $description = 'Rewrite pre-rebrand SunBet data rows to KemerBet (settings, menus, keyword replies, owner email)';

    public function handle(SettingsService $settings): int
    {
        // 1) Seeded owner email.
        $owner = Admin::query()->where('email', 'owner@sunbet.et')->first();

        if ($owner !== null) {
            $owner->update(['email' => 'owner@kemerbet.co', 'name' => 'KemerBet Owner']);
            $this->line('owner email → owner@kemerbet.co');
        }

        // 2) Settings: welcome message text + Web App URL.
        $welcome = (array) $settings->get('welcome.message', []);
        $rebranded = array_map(fn ($text) => is_string($text) ? $this->rebrand($text) : $text, $welcome);

        if ($rebranded !== $welcome) {
            $settings->set('welcome.message', $rebranded);
            $this->line('settings: welcome.message rebranded');
        }

        $webapp = (string) $settings->get('webapp.url', '');

        if ($webapp === '' || str_contains($webapp, 'sunbet')) {
            $settings->set('webapp.url', 'https://kemerbet.co/en/sport');
            $this->line('settings: webapp.url → https://kemerbet.co/en/sport');
        }

        // 3) Menu items: translations, generic URL rebrand, then the specific targets.
        foreach (MenuItem::query()->with('translations')->get() as $item) {
            foreach ($item->translations as $translation) {
                $updates = array_filter([
                    'label' => $this->rebrand((string) $translation->label),
                    'reply_text' => $translation->reply_text !== null ? $this->rebrand($translation->reply_text) : null,
                ], fn ($v, $k) => $v !== $translation->{$k}, ARRAY_FILTER_USE_BOTH);

                if ($updates !== []) {
                    $translation->update($updates);
                }
            }

            if ($item->url !== null && str_contains($item->url, 'sunbet')) {
                $item->update(['url' => $this->rebrand($item->url)]);
            }
        }

        $this->retargetMenuUrls();

        // 4) Keyword replies.
        foreach (KeywordReply::query()->with('translations')->get() as $rule) {
            foreach ($rule->translations as $translation) {
                $new = $this->rebrand($translation->reply_text);

                if ($new !== $translation->reply_text) {
                    $translation->update(['reply_text' => $new]);
                }
            }

            $buttons = $rule->buttons;

            if ($buttons !== null) {
                $json = $this->rebrand(json_encode($buttons, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $rule->update(['buttons' => json_decode($json, true)]);
            }
        }

        $settings->flush();
        $this->info('Rebrand pass complete (idempotent — re-running is safe).');

        return self::SUCCESS;
    }

    /** Product-URL retargeting for the seeded starter menu, matched by label. */
    private function retargetMenuUrls(): void
    {
        $targets = [
            // Matches both the new label and the generically-rebranded old one
            // ("Visit kemerbet.co"), which is then normalized below.
            ['label_contains' => 'Visit', 'url' => 'https://kemerbet.co', 'action' => MenuActionType::Url],
            ['label_contains' => 'Sportsbook', 'url' => 'https://kemerbet.co/en/sport', 'action' => MenuActionType::Url],
            ['label_contains' => 'Casino', 'url' => 'https://kemerbet.co/en/fastgames-lobby/All/', 'action' => MenuActionType::Url],
        ];

        foreach ($targets as $target) {
            $items = MenuItem::query()
                ->whereHas('translations', fn ($q) => $q->where('label', 'like', '%'.$target['label_contains'].'%'))
                ->get();

            foreach ($items as $item) {
                $item->update(['action_type' => $target['action'], 'url' => $target['url']]);

                if ($target['label_contains'] === 'Visit') {
                    foreach ($item->translations as $translation) {
                        if (str_contains($translation->label, 'kemerbet.co')) {
                            $translation->update([
                                'label' => str_replace('Visit kemerbet.co', 'Visit KemerBet', $translation->label),
                            ]);
                        }
                    }
                }

                // The old demo "Casino info" was a reply item; as a URL button it
                // carries no reply text, and the label drops the "info".
                if ($target['label_contains'] === 'Casino') {
                    foreach ($item->translations as $translation) {
                        $translation->update([
                            'label' => str_replace([' info', ' መረጃ'], '', $translation->label),
                            'reply_text' => null,
                        ]);
                    }
                }

                $this->line("menu: '{$target['label_contains']}' → {$target['url']}");
            }
        }
    }

    private function rebrand(string $text): string
    {
        return str_replace(
            ['sunbet.et', 'SunBet', 'SUNBET', 'sunbet'],
            ['kemerbet.co', 'KemerBet', 'KEMERBET', 'kemerbet'],
            $text,
        );
    }
}
