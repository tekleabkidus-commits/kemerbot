<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AutomationTrigger;
use App\Models\Automation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Automation>
 */
class AutomationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'trigger' => AutomationTrigger::UserJoined,
            'trigger_config' => null,
            'cooldown_days' => 0,
            'is_active' => true,
        ];
    }

    public function inactiveTrigger(int $inactiveDays = 14): static
    {
        return $this->state([
            'trigger' => AutomationTrigger::Inactive,
            'trigger_config' => ['inactive_days' => $inactiveDays],
            'cooldown_days' => 30,
        ]);
    }

    public function paused(): static
    {
        return $this->state(['is_active' => false]);
    }
}
