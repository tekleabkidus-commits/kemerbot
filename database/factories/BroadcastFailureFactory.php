<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FailureCategory;
use App\Models\Broadcast;
use App\Models\BroadcastFailure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BroadcastFailure>
 */
class BroadcastFailureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'broadcast_id' => Broadcast::factory(),
            'user_id' => User::factory(),
            'tg_error_code' => 400,
            'category' => FailureCategory::Other,
            'sanitized_error' => 'Bad Request: chat not found',
            'attempts' => 1,
            'first_failed_at' => now(),
            'last_failed_at' => now(),
        ];
    }

    public function blocked(): static
    {
        return $this->state([
            'tg_error_code' => 403,
            'category' => FailureCategory::Blocked,
            'sanitized_error' => 'Forbidden: bot was blocked by the user',
        ]);
    }
}
