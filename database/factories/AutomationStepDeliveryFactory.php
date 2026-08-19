<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AutomationDeliveryStatus;
use App\Models\AutomationStep;
use App\Models\AutomationStepDelivery;
use App\Models\AutomationUserState;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationStepDelivery>
 */
class AutomationStepDeliveryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'automation_user_state_id' => AutomationUserState::factory(),
            'automation_step_id' => AutomationStep::factory(),
            'user_id' => User::factory(),
            'status' => AutomationDeliveryStatus::Queued,
            'attempt_count' => 0,
            'queued_at' => now(),
        ];
    }
}
