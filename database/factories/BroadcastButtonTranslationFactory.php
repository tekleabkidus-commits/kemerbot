<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BotLanguage;
use App\Models\BroadcastButton;
use App\Models\BroadcastButtonTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BroadcastButtonTranslation>
 */
class BroadcastButtonTranslationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'broadcast_button_id' => BroadcastButton::factory(),
            'lang' => BotLanguage::En,
            'label' => fake()->words(2, true),
        ];
    }

    public function amharic(): static
    {
        return $this->state([
            'lang' => BotLanguage::Am,
            'label' => 'አሁን ይወራረዱ',
        ]);
    }
}
