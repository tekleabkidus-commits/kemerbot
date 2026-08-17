<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TrackingLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackingLink>
 */
class TrackingLinkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->regexify('[a-z0-9]{8}'),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'clicks_count' => 0,
            'joins_count' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
