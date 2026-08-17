<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BotLanguage;
use App\Models\KeywordReply;
use App\Models\KeywordReplyTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KeywordReplyTranslation>
 */
class KeywordReplyTranslationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'keyword_reply_id' => KeywordReply::factory(),
            'lang' => BotLanguage::En,
            'reply_text' => fake()->sentence(),
        ];
    }

    public function amharic(): static
    {
        return $this->state([
            'lang' => BotLanguage::Am,
            'reply_text' => 'እናመሰግናለን!',
        ]);
    }
}
