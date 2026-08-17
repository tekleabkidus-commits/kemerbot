<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BotLanguage;
use App\Models\Broadcast;
use App\Models\BroadcastTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BroadcastTranslation>
 */
class BroadcastTranslationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'broadcast_id' => Broadcast::factory(),
            'lang' => BotLanguage::En,
            'text' => 'Hello {first_name}! '.fake()->sentence(),
            'media_file_id' => null,
        ];
    }

    public function amharic(): static
    {
        return $this->state([
            'lang' => BotLanguage::Am,
            'text' => 'ሰላም {first_name}! አዲስ ዜና አለን።',
        ]);
    }
}
