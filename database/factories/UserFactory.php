<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tg_chat_id' => fake()->unique()->numberBetween(100_000_000, 9_999_999_999),
            'first_name' => fake()->firstName(),
            'username' => fake()->optional(0.7)->userName(),
            'language' => 'en',
            'source' => null,
            'referred_by_user_id' => null,
            'joined_at' => now(),
            'last_active_at' => now(),
            'blocked_bot' => false,
            'in_channel' => false,
        ];
    }

    public function amharic(): static
    {
        return $this->state(['language' => 'am']);
    }

    public function blocked(): static
    {
        return $this->state([
            'blocked_bot' => true,
            'blocked_at' => now(),
        ]);
    }

    public function inChannel(): static
    {
        return $this->state([
            'in_channel' => true,
            'channel_checked_at' => now(),
        ]);
    }

    public function fromSource(string $source): static
    {
        return $this->state(['source' => $source]);
    }

    public function inactiveForDays(int $days): static
    {
        return $this->state(['last_active_at' => now()->subDays($days)]);
    }
}
