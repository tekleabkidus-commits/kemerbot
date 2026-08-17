<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\KeywordMatchType;
use App\Models\KeywordReply;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KeywordReply>
 */
class KeywordReplyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'keywords' => [fake()->unique()->word()],
            'match_type' => KeywordMatchType::Exact,
            'position' => 0,
            'media_file_id' => null,
            'buttons' => null,
            'is_active' => true,
        ];
    }

    public function contains(): static
    {
        return $this->state(['match_type' => KeywordMatchType::Contains]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
