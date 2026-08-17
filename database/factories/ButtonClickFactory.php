<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Broadcast;
use App\Models\BroadcastButton;
use App\Models\ButtonClick;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ButtonClick>
 */
class ButtonClickFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'broadcast_id' => Broadcast::factory(),
            // Resolved after broadcast_id, so the button belongs to the same broadcast.
            'button_id' => fn (array $attributes) => BroadcastButton::factory()
                ->callback()
                ->create(['broadcast_id' => $attributes['broadcast_id']])
                ->id,
            'clicked_at' => now(),
        ];
    }
}
