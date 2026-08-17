<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MessageDirection;
use App\Models\Admin;
use App\Models\TelegramMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelegramMessage>
 */
class TelegramMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tg_message_id' => fake()->unique()->numberBetween(1, 2_000_000_000),
            'direction' => MessageDirection::Inbound,
            'type' => 'text',
            'text' => fake()->sentence(),
            'media_meta' => null,
            'admin_id' => null,
            'sent_at' => now(),
        ];
    }

    public function adminOutbound(): static
    {
        return $this->state([
            'direction' => MessageDirection::AdminOutbound,
            'admin_id' => Admin::factory(),
        ]);
    }
}
