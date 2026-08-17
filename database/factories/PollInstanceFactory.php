<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Poll;
use App\Models\PollInstance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PollInstance>
 */
class PollInstanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'poll_id' => Poll::factory(),
            'user_id' => User::factory(),
            'tg_poll_id' => (string) fake()->unique()->numberBetween(1_000_000_000, 9_999_999_999),
            'sent_at' => now(),
        ];
    }
}
