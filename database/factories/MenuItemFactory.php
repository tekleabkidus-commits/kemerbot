<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MenuActionType;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'position' => 0,
            'action_type' => MenuActionType::Reply,
            'url' => null,
            'media_file_id' => null,
            'is_active' => true,
        ];
    }

    public function submenu(): static
    {
        return $this->state(['action_type' => MenuActionType::Submenu]);
    }

    public function url(string $url = 'https://sunbet.et'): static
    {
        return $this->state([
            'action_type' => MenuActionType::Url,
            'url' => $url,
        ]);
    }

    public function webapp(string $url = 'https://sunbet.et'): static
    {
        return $this->state([
            'action_type' => MenuActionType::Webapp,
            'url' => $url,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
