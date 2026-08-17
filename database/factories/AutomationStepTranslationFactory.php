<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BotLanguage;
use App\Models\AutomationStep;
use App\Models\AutomationStepTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationStepTranslation>
 */
class AutomationStepTranslationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'automation_step_id' => AutomationStep::factory(),
            'lang' => BotLanguage::En,
            'text' => fake()->sentence(),
        ];
    }

    public function amharic(): static
    {
        return $this->state([
            'lang' => BotLanguage::Am,
            'text' => 'እንኳን ደህና መጡ! ዛሬ ይጫወቱ።',
        ]);
    }
}
