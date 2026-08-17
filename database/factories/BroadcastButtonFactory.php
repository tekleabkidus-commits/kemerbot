<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ButtonKind;
use App\Models\Broadcast;
use App\Models\BroadcastButton;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BroadcastButton>
 */
class BroadcastButtonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'broadcast_id' => Broadcast::factory(),
            'row' => 0,
            'position' => 0,
            'kind' => ButtonKind::Url,
            'url' => 'https://sunbet.et',
        ];
    }

    public function callback(): static
    {
        return $this->state([
            'kind' => ButtonKind::Callback,
            'url' => null,
        ]);
    }
}
