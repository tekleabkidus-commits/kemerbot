<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Automation;
use App\Models\AutomationStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationStep>
 */
class AutomationStepFactory extends Factory
{
    public function definition(): array
    {
        return [
            'automation_id' => Automation::factory(),
            'step_no' => 0,
            'delay_hours' => 24,
            'media_file_id' => null,
            'buttons' => null,
        ];
    }

    public function withButtons(): static
    {
        return $this->state([
            'buttons' => [[
                'kind' => 'url',
                'url' => 'https://sunbet.et',
                'label' => ['en' => 'Visit SunBet', 'am' => 'SunBet ይጎብኙ'],
            ]],
        ]);
    }
}
