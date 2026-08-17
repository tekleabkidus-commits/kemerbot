<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AutomationUserStatus;
use App\Models\Automation;
use App\Models\AutomationUserState;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationUserState>
 */
class AutomationUserStateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'automation_id' => Automation::factory(),
            'user_id' => User::factory(),
            'current_step_no' => 0,
            'status' => AutomationUserStatus::Active,
            'triggered_at' => now(),
            'last_step_sent_at' => null,
            'next_step_at' => now()->addDay(),
            'completed_at' => null,
            'cooldown_until' => null,
            'trigger_key' => fake()->uuid(),
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => AutomationUserStatus::Completed,
            'completed_at' => now(),
            'next_step_at' => null,
        ]);
    }

    public function cooldown(): static
    {
        return $this->state([
            'status' => AutomationUserStatus::Cooldown,
            'completed_at' => now(),
            'next_step_at' => null,
            'cooldown_until' => now()->addDays(30),
        ]);
    }
}
