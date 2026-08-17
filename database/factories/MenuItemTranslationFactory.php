<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BotLanguage;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItemTranslation>
 */
class MenuItemTranslationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'menu_item_id' => MenuItem::factory(),
            'lang' => BotLanguage::En,
            'label' => fake()->words(2, true),
            'reply_text' => fake()->sentence(),
        ];
    }

    public function amharic(): static
    {
        return $this->state([
            'lang' => BotLanguage::Am,
            'label' => 'ምናሌ',
            'reply_text' => 'ሰላም! እንኳን ደህና መጡ።',
        ]);
    }
}
