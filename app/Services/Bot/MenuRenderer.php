<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Enums\BotLanguage;
use App\Enums\MenuActionType;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * Renders the menu tree as Telegram inline keyboards in the user's language
 * (spec §5.1). Back button auto-added on submenus; callback protocol per §9.
 */
final class MenuRenderer
{
    public const ROOT = 0;

    public function __construct(private readonly BotLocaleResolver $locale) {}

    /** Keyboard for the main (root) menu, or null when no active items exist. */
    public function rootKeyboard(BotLanguage $lang): ?array
    {
        $items = $this->activeChildren(null);

        return $items->isEmpty() ? null : $this->keyboard($items, $lang, backTo: null);
    }

    /** Keyboard for a submenu's children, with a Back button appended. */
    public function submenuKeyboard(MenuItem $item, BotLanguage $lang): array
    {
        $items = $this->activeChildren($item->id);

        // Back re-renders the menu that contains $item.
        return $this->keyboard($items, $lang, backTo: $item->parent_id ?? self::ROOT);
    }

    /** @param  Collection<int, MenuItem>  $items */
    private function keyboard(Collection $items, BotLanguage $lang, ?int $backTo): array
    {
        $rows = $items
            ->map(fn (MenuItem $item) => [$this->button($item, $lang)])
            ->values()
            ->all();

        if ($backTo !== null) {
            $rows[] = [[
                'text' => $this->locale->uiText('back', $lang),
                'callback_data' => 'menu:'.$backTo,
            ]];
        }

        return ['inline_keyboard' => $rows];
    }

    private function button(MenuItem $item, BotLanguage $lang): array
    {
        $translation = $this->locale->pickTranslation($item->translations, $lang);
        $label = $translation?->label ?? '—';

        return match ($item->action_type) {
            MenuActionType::Url => ['text' => $label, 'url' => (string) $item->url],
            MenuActionType::Webapp => ['text' => $label, 'web_app' => ['url' => (string) $item->url]],
            MenuActionType::Reply,
            MenuActionType::Submenu => ['text' => $label, 'callback_data' => 'menu:'.$item->id],
        };
    }

    /** @return Collection<int, MenuItem> */
    private function activeChildren(?int $parentId): Collection
    {
        return MenuItem::query()
            ->where('is_active', true)
            ->when(
                $parentId === null,
                fn ($q) => $q->whereNull('parent_id'),
                fn ($q) => $q->where('parent_id', $parentId),
            )
            ->orderBy('position')
            ->orderBy('id')
            ->with('translations')
            ->get();
    }
}
